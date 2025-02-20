<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Libs\Device;
use App\Libs\RedisClient;
use App\Models\Devices;
use App\Models\DevicesOnlineLogs;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
header('Access-Control-Allow-Origin:*');


/**
 * 设备管理接口
 */
class DeviceController extends Controller
{

    /**
     * 添加用户到设备
     */
    public function addUserToDevice(Request $request){
    // public function addUserToDeviceNew(Request $request){
        $param = $request->post();

        $deviceInfo = DB::table('devices')->where('sign',1)->where('code',$param['sn'])->where(function ($query) {
            $query->where('type', 1)->orWhere(function ($query) {
                $query->where('type', 2);
            });
        })->first();

        if(!$deviceInfo)
            show_msg('设备不存在或者被禁用了','10001');

        if(!$deviceInfo->mid) //无商户的设备走之前逻辑
            $this->addUserToDeviceBac($param);
//            show_msg('设备未绑定商户','10002');

        $deviceList = DB::table('devices')->where('sign',1)->where('mid',$deviceInfo->mid)->where(function ($query) {
            $query->where('type', 1)->orWhere(function ($query) {
                $query->where('type', 2);
            });
        })->select('code')->get();

        $deviceList = deJson(enJson($deviceList));

        if(!$res = DB::table('device_userid')->where('mid',$deviceInfo->mid)->first()){
            DB::table('device_userid')->insert([
                'mid'=>$deviceInfo->mid,
                'code'=>10
            ]);
            $code = 10;
        }else{
            $code = $res->code + 1;
            DB::table('device_userid')->where('mid', $deviceInfo->mid)->update([
                'code'=>$code
            ]);
        }

        $userInfo = array(
            'id'       => 0,
            'type'     => 1,
            'code'     => $code,
            'name'     => $param['username'],
            'idNo'     => $param['idNo'],
            'sex'      => 'male',
            'birthday' => date('Y-m-d'),
//            'head_url' => $param['img_url'],
            'img_url' => $param['img_url'],
        );

        $device = new Device();

        foreach ($deviceList as $val){
            $device->addMem($val['code'], $userInfo);
            usleep(100000); //休眠0.1秒
        }

        returnSuccess();
    }

    /**
     * 添加用户到设备-无商户的单设备
     */
    // public function addUserToDevice(Request $request){
    //     $param = $request->post();
   public function addUserToDeviceBac($param){
        if(!$res = DB::table('device_userid')->where('sn',$param['sn'])->first()){
            DB::table('device_userid')->insert([
                'sn'=>$param['sn'],
                'code'=>10
            ]);
            $code = 10;
        }else{
            $code = $res->code + 1;
            DB::table('device_userid')->where('sn', $param['sn'])->update([
                'code'=>$code
            ]);
        }

        $userInfo = array(
            'id'       => 0,
            'type'     => 1,
            'code'     => $code,
            'name'     => $param['username'],
            'idNo'     => $param['idNo'],
            'sex'      => 'male',
            'birthday' => date('Y-m-d'),
//            'head_url' => $param['img_url'],
            'img_url' => $param['img_url'],
        );

        $device = new Device();

        $device->addMem($param['sn'], $userInfo);

        returnSuccess();
    }

    /**
     * 开启or关闭第一路输入控制输出
     * @param Request $request
     */
    public function onOffCommand(Request $request){
        $type = $request->post('type','');
        if($type=='on'){
            $command = '55 AA AA AA AA AA 01 07 31 00 00 00 00 01 00 E1 16'; //开启第一路输入控制输出
        }elseif($type='off'){
            $command = '55 AA AA AA AA AA 01 07 31 00 00 00 00 00 00 E0 16'; //关闭第一路输入控制输出
        }

//        $command = '55 AA AA AA AA AA 04 01 0F BB 16'; //有输入信号时继电器会主动向连接的服务器间隔发送 3条当前状态报文（服务器如果回复应答就只发1条，回复：55 AA AA AA AA AA 04 01 0F BB 16）
//        $command = '55 AA AA AA AA AA 03 06 90 00 00 00 00 02 42 16'; //开闸指令。发送第1路 点动2秒关闭

        $sn = '9c-a5-25-d7-4c-7d';
        $this->deviceCommand($sn, $command);
    }

    /**
     * 车闸控制器指令下发
     * @param $sn
     * @param $command
     */
    public function deviceCommand($sn, $command){
        $device = new Device();

        $command = hex2bin(str_replace(' ','',$command));
        $device->pushOrder($sn, $command);
    }

    public function openDoorLog(Request $request){
        $sn = $request->post('sn');
        $data = [
            'sn'=>$sn,
            'created_at'=>date('Y-m-d H:i:s'),
        ];

        $id = DB::table('opengate_event')->insertGetId($data);


        //检查是否需要开闸，如果需要开闸的话开闸
        $time = date('Y-m-d H:i:s', time()-60*2);
        $res = DB::table('access_records')->where('device_code',$sn)->where('created_at','>',$time)->where('status',1)->orderByDesc('id')->first();

        if($res){
            $device = new Device();
            $deviceType = DB::table('devices')->where('code',$sn)->where('status',1)->where('sign',1)->select('type')->first();
            if(!$deviceType)
                show_msg('设备离线或者禁用了', '10001');

            if($deviceType->type == 3){
                $command = [
                    "id"=>12,
                    "method"=>"devDoor.openOnce",
                    "params"=>[
                        "channel"=>0
                    ]
                ];
                $command = enJson($command);
            }elseif($deviceType->type == 4){
                $command = '55 AA AA AA AA AA 03 06 90 00 00 00 00 02 42 16'; //开闸指令。发送第1路 点动2秒关闭
                $command = hex2bin(str_replace(' ','',$command));
            }

            $device->pushOrder($sn, $command);

            //开闸后修改下该条记录  最好放event里处理
            DB::transaction(function () use ($res, $id) {
                DB::table('opengate_event')->where('id',$id)->update(['result'=>2]);
                DB::table('access_records')->where('id',$res->id)->update(['status'=>2]);
            });
        }
    }

    /**
     * 展示大屏——各个商户信息统计
     */
    public function partInfo(Request $request){
        $pid = $request->get('pid', '');
        if(!$pid)
            show_json([],'pid不存在');

        $mid = DB::table('merchant_relations')->where('pid',$pid)->pluck('mid');
        $mid = deJson(enJson($mid));

        $data = DB::table('day_count')
            ->leftJoin('merchant','day_count.mid','=','merchant.id')
            ->whereIn('day_count.mid', $mid)->groupBy('day_count.mid','merchant.title')->get(
            array(
                DB::raw('day_count.mid'),
                DB::raw('merchant.title'),
                DB::raw('SUM(day_count.total) as total'),
                DB::raw('SUM(day_count.normal_total) as normal_total')
            )
        );

        $data = deJson(enJson($data));
        foreach ($data as $k=>$v){
            $data[$k]['abnormal_total'] = $v['total']-$v['normal_total'];
        }

        returnSuccess($data);
    }

    /**
     * 展示大屏——左上角统计模块
     * @param Request $request
     */
    public function totalInfo(Request $request){
        $pid = $request->get('pid', '');
        if(!$pid)
            show_json([],'pid不存在');

        $mid = DB::table('merchant_relations')->where('pid',$pid)->pluck('mid');
        $mid = deJson(enJson($mid));
        $totalInfo = DB::table('day_count')->whereIn('mid', $mid)->first(
            array(
                DB::raw('SUM(normal_total) as normal_total'),
                DB::raw('SUM(temperature_abnormal_total) as temperature_abnormal_total'),
                DB::raw('SUM(ankangcode_abnormal_total) as yellowcode_abnormal_total'),
                DB::raw('SUM(nucleic_acid_abnormal_total) as nucleic_acid_abnormal_total')
            )
        );
        if($totalInfo){
            $totalInfo->redcode_abnormal_total="0";
        }

        $endDate = date('Y-m-d 00:00:00',strtotime(date('Y-m-d')) - 86400);
        $startDate = date('Y-m-d 00:00:00',strtotime(date('Y-m-d')) - 86400*7);
        $date = [$startDate,$endDate];

        $sevenData = DB::table('day_count')->whereBetween('date',$date)->whereIn('mid', $mid)->groupBy('date')->get(
            array(
                DB::raw('date'),
                DB::raw('SUM(total) as total'),
                DB::raw('SUM(normal_total) as normal_total')
            )
        );
        $sevenData = deJson(enJson($sevenData));
        foreach ($sevenData as $k=>$v){
            $sevenData[$k]['abnormal_total'] = $v['total']-$v['normal_total'].'';
            $sevenData[$k]['date'] = substr(substr($v['date'],5),0,-9);
        }

        returnSuccess([
            'totalInfo'=>$totalInfo,
            'sevenData'=>$sevenData
        ]);
    }
    /**
     * 展示大屏——异常日志模块
     * @param Request $request
     */
    public function zsDapingAbnormalLog(Request $request){
        $pid = $request->get('pid', '');
        if(!$pid)
            show_json([],'pid不存在');

        $mid = DB::table('merchant_relations')->where('pid',$pid)->pluck('mid');
        $mid = deJson(enJson($mid));
        $personLog = DB::table('access_records')->where('car_code','')->whereIn('mid', $mid)
            ->whereRaw('(nucleic_acid_result = ? or nucleic_acid_result = ? or ankang_code = ? or ankang_code = ? or temperature >= ?)', [2,3,2,3,38])
            ->orderByDesc('created_at')
            ->limit(9)
            ->select('username','created_at','nucleic_acid_result','ankang_code','temperature','check_point')
            ->get();
        $carLog = DB::table('access_records')->where('car_code','<>','')->whereIn('mid', $mid)
            ->whereRaw('(nucleic_acid_result = ? or nucleic_acid_result = ? or ankang_code = ? or ankang_code = ? or temperature >= ?)', [2,3,2,3,38])
            ->orderByDesc('created_at')
            ->limit(9)
            ->select('username','created_at','nucleic_acid_result','ankang_code','temperature','check_point')
            ->get();

        $personLog = deJson(enJson($personLog));
        $carLog = deJson(enJson($carLog));
        foreach ($personLog as $k=>$v){
            $personLog[$k]['created_at'] = substr(substr($v['created_at'],5),0,-3);
        }
        foreach ($carLog as $k=>$v){
            $personLog[$k]['created_at'] = substr(substr($v['created_at'],5),0,-3);
        }

        returnSuccess(['personLog'=>$personLog,'carLog'=>$carLog]);
    }

    /**
     * 展示大屏——中间统计模块
     * @param Request $request
     */
    public function zsDapingCount(Request $request){
        $pid = $request->get('pid', '');
        if(!$pid)
            show_json([],'pid不存在');

        $mid = DB::table('merchant_relations')->where('pid',$pid)->pluck('mid');
        $mid = deJson(enJson($mid));

        $dateToday = date('Y-m-d 00:00:00');
        $dataYesterday = date('Y-m-d 00:00:00',strtotime(date('Y-m-d')) - 86400);
        $week = $this->week();
        $month = $this->month();

        $todaycount = DB::table('day_count')->where('date',$dateToday)->whereIn('mid', $mid)->first(
            array(
                DB::raw('SUM(total) as total'),
                DB::raw('SUM(normal_total) as normal_total')
            )
        );
        $yesterdaycount = DB::table('day_count')->where('date',$dataYesterday)->whereIn('mid', $mid)->first(
            array(
                DB::raw('SUM(total) as total'),
                DB::raw('SUM(normal_total) as normal_total')
            )
        );
        $weekcount = DB::table('day_count')->whereBetween('date',$week)->whereIn('mid', $mid)->first(
            array(
                DB::raw('SUM(total) as total'),
                DB::raw('SUM(normal_total) as normal_total')
            )
        );
        $monthcount = DB::table('day_count')->whereBetween('date',$month)->whereIn('mid', $mid)->first(
            array(
                DB::raw('SUM(total) as total'),
                DB::raw('SUM(normal_total) as normal_total')
            )
        );

        $data = [
            'todaycount'=>$todaycount ?: 0,
            'yesterdaycount'=>$yesterdaycount,
            'weekcount'=>$weekcount,
            'monthcount'=>$monthcount,
        ];

        returnSuccess($data);
    }

    public function dapingCount(Request $request){
        $mid = $request->get('mid', '');
        if(!$mid)
            show_json([],'mid不存在');

        $dateToday = date('Y-m-d 00:00:00');
        $dataYesterday = date('Y-m-d 00:00:00',strtotime(date('Y-m-d')) - 86400);
        $week = $this->week();
        $month = $this->month();

        $todaycount = DB::table('day_count')->where('date',$dateToday)->where('mid', $mid)->select('total','normal_total')->first();
        $yesterdaycount = DB::table('day_count')->where('date',$dataYesterday)->where('mid', $mid)->select('total','normal_total')->first();
        $weekcount = DB::table('day_count')->whereBetween('date',$week)->where('mid', $mid)->first(
                array(
                    DB::raw('SUM(total) as total'),
                    DB::raw('SUM(normal_total) as normal_total')
                )
            );
        $monthcount = DB::table('day_count')->whereBetween('date',$month)->where('mid', $mid)->first(
            array(
                DB::raw('SUM(total) as total'),
                DB::raw('SUM(normal_total) as normal_total')
            )
        );

        $data = [
            'todaycount'=>$todaycount ?: 0,
            'yesterdaycount'=>$yesterdaycount,
            'weekcount'=>$weekcount,
            'monthcount'=>$monthcount,
        ];

        returnSuccess($data);
    }

    public function week(){
        $timestr = time();
        $now_day = date('w',$timestr);
        //获取一周的第一天，注意第一天应该是星期天
        $sunday_str = $timestr - ($now_day-1)*60*60*24;
        $sunday = date('Y-m-d 00:00:00', $sunday_str);
        //获取一周的最后一天，注意最后一天是星期六
        $strday_str = $timestr + (6-$now_day+1)*60*60*24;
        $strday = date('Y-m-d 00:00:00', $strday_str);

        return [
            $sunday,
            $strday
        ];
//        return [
//            'monday'=>$sunday,
//            'sunday'=>$strday
//        ];
    }

    public function month(){
        $beginDate = date('Y-m-01 00:00:00',strtotime(date("Y-m-d")));
        $endDate = date('Y-m-d 00:00:00',strtotime("$beginDate + 1 month -1 day"));
        return [
            $beginDate,
            $endDate
        ];

//        return [
//            'beginDate'=>$beginDate,
//            'endDate'=>$endDate
//        ];
    }

    public function deviceInfo(Request $request){
        $sn = $request->post('sn','');

        $config = [
            'host'  => '127.0.0.1',
            'port'  => 6379,
            'db_id' => 0,
        ];
        $redis = new RedisClient($config);

        $res = DB::table('devices')->where('code',$sn)->select('mid','address')->first();
        if($res){
            $deviceInfo = ['mid'=>$res->mid,'address'=>$res->address];
            $redis->hSet('equipmentMerchantRelationship', $sn, json_encode($deviceInfo,JSON_UNESCAPED_UNICODE));
        }
    }

    public function access_records(Request $request){
        $param = $request->post();
        $now = date('Y-m-d H:i:s');

        $res = DB::table('devices')->where('code',$param['device_code'])->select('mid')->first();
        if($res){
            $param['mid'] = $res->mid;
            $param['created_at'] = $now;
            DB::table('access_records')->insert($param);
        }

        $this->dayCount($param);
    }

    public function dayCount($param){
        $date = date('Y-m-d');
        if(DB::table('day_count')->where('mid',$param['mid'])->where('date',$date)->first()){ //update
            DB::table('day_count')->where('mid',$param['mid'])->where('date',$date)->increment('total');
            if($param['nucleic_acid_result']==2 || $param['nucleic_acid_result']==3){ //核酸异常
                DB::table('day_count')->where('mid',$param['mid'])->where('date',$date)->increment('nucleic_acid_abnormal_total');
            }elseif($param['ankang_code']==2 || $param['ankang_code']==3){//安康码异常
                DB::table('day_count')->where('mid',$param['mid'])->where('date',$date)->increment('ankangcode_abnormal_total');
            }elseif($param['temperature'] >= 38){//体温异常
                DB::table('day_count')->where('mid',$param['mid'])->where('date',$date)->increment('temperature_abnormal_total');
            }else{//正常
                DB::table('day_count')->where('mid',$param['mid'])->where('date',$date)->increment('normal_total');
            }
        }else{//add
            if($param['nucleic_acid_result']==2 || $param['nucleic_acid_result']==3){ //核酸异常
                $data['nucleic_acid_abnormal_total'] = 1;
            }elseif($param['ankang_code']==2 || $param['ankang_code']==3){//安康码异常
                $data['ankangcode_abnormal_total'] = 1;
            }elseif($param['temperature'] >= 38){//体温异常
                $data['temperature_abnormal_total'] = 1;
            }else{//正常
                $data['normal_total'] = 1;
            }

            $data['total'] = 1;
            $data['mid'] = $param['mid'];
            $data['date'] = $date;

            DB::table('day_count')->insert($data);
        }
    }

    /**
     * [addDevice 上线新增设备]
     * @Author   heizi
     * @DateTime 2021-02-24T14:34:53+0800
     * @param    Request                  $request [description]
     */
    public function addDevice(Request $request)
    {
        $data = $request->post();
        Log::debug($data);
        // dd($data);
        if (empty($data)) {
            return -1;
        }
        switch ($data['type']) {
            case '1': //锐颖
                $code = $data['sn'];
                break;

                break;
            default:
                $code = '';
                break;
        }
        if ($code == '') {
            return -2;
        }
        $info = Devices::where('code', $code)->first();
        if (!$info) {
            //新增数据
            $ins_data = [
                'code'        => $code,
                'name'        => '锐颖',
                'type'        => $data['type'],
                'online_time' => date('Y-m-d H:i:s'),
                'created_at'  => date('Y-m-d H:i:s'),
            ];

            Devices::insert($ins_data);
            $this->d_online_log($code);
            return 1;
        } else {
            Log::debug('设备上线');
            //更新数据
            $upt_data = [
                'status'      => 1,
                'online_time' => date('Y-m-d H:i:s'),
            ];
            Devices::where('id', $info->id)->update($upt_data);
            $this->d_online_log($code);
            return 2;
        }
    }
    /**
     * [closeDevice 设备掉线处理]
     * @Author   heizi
     * @DateTime 2021-02-24T15:19:48+0800
     * @param    Request                  $request [description]
     * @return   [type]                            [description]
     */
    public function closeDevice(Request $request)
    {
        $data = $request->post();
        if (empty($data)) {
            return -1;
        }
        $code = $data['sn'];
        Log::debug('关闭设备');
        Log::debug($data);

        $config = array(
            'port'  => env('REDIS_PORT', '6379'),
            'host'  => env('REDIS_HOST', '127.0.0.1'),
            'db_id' => env('REDIS_DBID', 0),
        );
        $redis = new RedisClient($config);

        $redis->hdel('energy_device_heart', $code);

        $upt_data = [

            'status'     => 2,
            'close_time' => date('Y-m-d H:i:s'),
        ];
        $this->d_online_log($code, 2);
        Devices::where('code', $code)->update($upt_data);
        return 1;

    }
    /**
     * [d_online_log description]
     * @Author   heizi
     * @DateTime 2021-07-01T15:35:39+0800
     * @param    [type]                   $code [description]
     * @param    integer                  $type [description]
     * @return   [type]                         [1:上线  2：下线]
     */
    public function d_online_log($code, $type = 1)
    {

        $ins_data = [
            'code'    => $code,
            'type'    => $type,
            'addtime' => date('Y-m-d H:i:s'),
        ];

        DevicesOnlineLogs::insert($ins_data);
    }
}
