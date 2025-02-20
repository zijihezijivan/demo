<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Libs\Aes;
use App\Libs\Device;
use App\Libs\RedisClient;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
header('Access-Control-Allow-Origin:*');

class CarAccountController  extends Controller
{
    public function gkmtest(Request $request){
        $param = $request->post();
        $aes = new Aes();

        $userInfo = [
            'name'=>$param['username'],
            'idcard'=>$param['idcard'],
            'type'=>'1',
        ];
        $userInfo = $aes->encrypt(enJson($userInfo));

        $data = [
            'key'=>'eerXg7yWsUSuKWP2',
            'device_code'=>$param['device_code'],
            'data'=>$userInfo,
        ];

        $akmUrl = 'http://47.111.173.101:7090/api/v1/anhui/getakm';
        $gkmRes = deJson(post($akmUrl, $data, false, false, false));

        if($gkmRes['code']!='10000')
            returnFail('10060','安康码or核酸检测报告接口请求失败',[$gkmRes]);

        $gkmRes = deJson($aes->decrypt($gkmRes['data']));

        dd($gkmRes);
    }

    /**
     * test
     */
    public function test(){
        $device = new Device();

        $command = [
            "id"=>12,
            "method"=>"devDoor.openOnce",
            "params"=>[
                "channel"=>0
            ]
        ];

        $device->pushOrder('5L24R550047', enJson($command));
    }


    /**
     * 二维码连接请求的接口
     * @param Request $request
     */
    public function qrCode(Request $request){
        $param = $request->all();
        if((!isset($param['mid']) || !$param['mid']) || (!isset($param['device_code']) || !$param['device_code']))
            returnFail('10060','参数有误');

        if(strpos($_SERVER['HTTP_USER_AGENT'], 'MicroMessenger')!==false){ //微信客户端
            $redirect_uri = 'http://dev.hongdaosz.art/api/Login/akm_chechang_login?mid='.$param['mid'].'&device_code='.$param['device_code'];
            $redirect_uri = urlencode($redirect_uri);
            $url = 'https://open.weixin.qq.com/connect/oauth2/authorize?appid=wx3679b91facdc6b26&redirect_uri='.$redirect_uri.'&response_type=code&scope=snsapi_base&state=123#wechat_redirect';
            header("Location: $url");
        }elseif (strpos($_SERVER['HTTP_USER_AGENT'], 'Alipay')!==false){ //支付宝

        }else{
            returnFail('10060','请使用微信或支付宝扫码');
        }
    }

    /**
     * 根据openid获取用户信息
     * @param Request $request
     */
    public function getUserInfo(Request $request){
        $openid = $request->get('openid');
        $res = DB::table('wechat_user')->where('openid',$openid)->where('is_deleted',0)->select('username','idcard','phone','car_code')->first();

        returnSuccess($res);
    }

    /**
     * 车行人员记录
     * @param Request $request
     */
    public function vehiclePersonnelRecords(Request $request){
        $param = $request->post();

        $wechatUser = [
            'openid'=>$param['openid'],
            'username'=>$param['username'],
            'idcard'=>$param['idcard'],
            // 'phone'=>$param['phone'],
            'phone'=>isset($param['phone']) ? $param['phone'] : '',
            'car_code'=>$param['car_code'] ? : '',
            'created_at'=>date('Y-m-d H:i:s')
        ];
        if(DB::table('wechat_user')->where('openid',$param['openid'])->where('is_deleted',0)->first()){//upd操作
            unset($wechatUser['created_at']);
            DB::table('wechat_user')->where('openid',$param['openid'])->update($wechatUser);
        }else{//添加操作
            DB::table('wechat_user')->insert($wechatUser);
        }

        $aes = new Aes();

        $userInfo = [
            'name'=>$param['username'],
            'idcard'=>$param['idcard'],
            'type'=>'1',
        ];
        $userInfo = $aes->encrypt(enJson($userInfo));

        $data = [
            'key'=>'eerXg7yWsUSuKWP2',
            'device_code'=>$param['device_code'],
            'data'=>$userInfo,
        ];

        $akmUrl = 'http://47.111.173.101:7090/api/v1/anhui/getakm';
        $hesuanUrl = 'http://47.111.173.101:7090/api/v1/anhui/geths';
        $akmRes = deJson(post($akmUrl, $data, false, false, false));
        $hesuanRes = deJson(post($hesuanUrl, $data, false, false, false));

        if($akmRes['code']!='10000' || $hesuanRes['code']!='10000')
            returnFail('10060','安康码or核酸检测报告接口请求失败');

        $akmRes = deJson($aes->decrypt($akmRes['data']));
        $hesuanRes = deJson($aes->decrypt($hesuanRes['data']));

        if(!$akmRes['flag'] || !$hesuanRes['flag']){
            if($akmRes['errCode']=='400')
                returnFail('10005','姓名与证件号不匹配');

            returnFail('10060','安康码or核酸检测报告接口请求失败');
        }

        $health_code = isset($akmRes['data']['healthLevel']) ? $akmRes['data']['healthLevel'] : -1;
        switch ($health_code) {
            case 2:
                $health_msg = "健康码色异常";
                break;
            case 3:
                $health_msg = "健康码色异常";
                break;
            default:
                $health_msg = "系统查询失败，请联系运维人员";
                break;
        }
        if($health_code != 1)
            returnFail('10001','安康码异常',[
                'health_code'=>$health_code,
                'health_msg'=>$health_msg,
            ]);

        //核酸检测结果超48h不予开闸
        $config_time = DB::table('devices')->where('code',$param['device_code'])->first('hs_time');
        $config_time = $config_time->hs_time;
        $natLastDate = date('Y-m-d H:i:s', substr($hesuanRes['testingDatetime'], 0, 10));
        if($config_time){ //0表示不设置核酸时间限制
            $time48h = date('Y-m-d H:i:s', time()-3600*$config_time);
            if($natLastDate < $time48h)
                returnFail('10002','核酸核验已过'.$config_time.'h',[
                    'is_timeout'=>1,
                    'timeout_msg'=>'核酸核验已过时效',
                ]);
        }

        switch ($hesuanRes['signName']) {
            case '阴性':
                $nucleic_acid_result=1;
                break;
            case '阳性':
                $nucleic_acid_result=2;
                $hesuan_msg = "核酸阳性禁止通行";
                break;
            default:
                $nucleic_acid_result=-1;
                $hesuan_msg = "系统查询失败，请联系运维人员";
                break;
        }

        if($nucleic_acid_result != 1)
            returnFail('10003','核酸异常',[
                'nucleic_acid_result'=>$nucleic_acid_result,
                'hesuan_msg'=>$hesuan_msg,
            ]);

        $data = [
            'mid'=>$param['mid'],
            'username'=>$param['username'],
            'idcard'=>$param['idcard'],
            // 'phone'=>$param['phone'],
            'phone'=>isset($param['phone']) ? $param['phone'] : '',
            'car_code'=>$param['car_code'] ? : '',
            'nucleic_acid_result'=>$nucleic_acid_result,
            'ankang_code'=>$akmRes['data']['healthLevel'],
            'natLastDate'=>date('Y-m-d H:i:s', substr($hesuanRes['testingDatetime'], 0, 10)),
            'device_code'=>$param['device_code'],
            'created_at'=>date('Y-m-d H:i:s'),
        ];
        $access_records_id = DB::table('access_records')->insertGetId($data);

        //websocket通知大屏
        $config = [
            'host'  => '127.0.0.1',
            'port'  => 6379,
            'db_id' => 0,
        ];
        $redis = new RedisClient($config);
        if ($deviceInfo = $redis->hGet('equipmentMerchantRelationship', $data['device_code'])) {
            $deviceInfo      = json_decode($deviceInfo, true);
            $data['address'] = $deviceInfo['address'];
            $data['picture'] = '';

            $device = new Device();
            $device->pushOrder($deviceInfo['mid'], json_encode($data));
        }

        if($param['car_code']){//检查是否要开闸
            //检查开闸日志表，看 该设备 有无 时间在两分钟前（>两分钟前的时间）且未开闸的记录，有的话下发开闸指令
            $time = date('Y-m-d H:i:s', time()-60*2);
            $res = DB::table('opengate_event')->where('sn',$param['device_code'])->where('created_at','>',$time)->where('result',1)->orderByDesc('id')->first();

            if($res){
                $device = new Device();
                $deviceType = DB::table('devices')->where('code',$param['device_code'])->where('status',1)->where('sign',1)->select('type')->first();
                if(!$deviceType)
                    show_msg('设备离线或者禁用了', '10004');

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

                $device->pushOrder($param['device_code'], $command);

                //开闸后修改下该条记录  最好放event里处理
                DB::transaction(function () use ($res, $access_records_id) {
                    DB::table('opengate_event')->where('id',$res->id)->update(['result'=>2]);
                    DB::table('access_records')->where('id',$access_records_id)->update(['status'=>2]);
                });
            }
        }

        $time48h = date('Y-m-d H:i:s', time()-3600*48);
        $time72h = date('Y-m-d H:i:s', time()-3600*72);
        if($time48h <= $natLastDate){
            $status = 1;//48小时内
        }elseif($time72h <= $natLastDate && $natLastDate < $time48h){
            $status = 2;//72小时内
        }elseif($natLastDate < $time72h){
            $status = 3;//超72小时
        }


        returnSuccess(['natLastDate'=>$natLastDate, 'status'=>$status]);
    }

    public function openDoorEvent(Request $request){
        $sn = $request->post('sn');
        $data = [
            'sn'=>$sn,
            'created_at'=>date('Y-m-d H:i:s'),
        ];

        DB::table('opengate_event')->insert($data);

        //判断是否要开闸
        $device = new Device();

//        $command = '55 AA AA AA AA AA 01 07 31 00 00 00 00 01 00 E1 16'; //开启第一路输入控制输出
//        $command = '55 AA AA AA AA AA 01 07 31 00 00 00 00 00 00 E0 16'; //关闭第一路输入控制输出
//        $command = '55 AA AA AA AA AA 04 01 0F BB 16'; //有输入信号时继电器会主动向连接的服务器间隔发送 3条当前状态报文（服务器如果回复应答就只发1条，回复：55 AA AA AA AA AA 04 01 0F BB 16）
        $command = '55 AA AA AA AA AA 03 06 90 00 00 00 00 02 42 16'; //开闸指令。发送第1路 点动2秒关闭

        $command = hex2bin(str_replace(' ','',$command));

        $device->pushOrder($sn, $command);
    }

/****************************************************************肥西高速平安社区车闸****************************************************************************/
    /**
     * 二维码连接请求的接口
     * @param Request $request
     */
    public function fxgsQrCode(Request $request){
        $param = $request->all();
        if((!isset($param['mid']) || !$param['mid']) || (!isset($param['device_code']) || !$param['device_code']))
            returnFail('10060','参数有误');

        if(strpos($_SERVER['HTTP_USER_AGENT'], 'MicroMessenger')!==false){ //微信客户端
            $redirect_uri = 'http://dev.hongdaosz.art/api/Login/fxgs_chechang_login?mid='.$param['mid'].'&device_code='.$param['device_code'];
            $redirect_uri = urlencode($redirect_uri);
            $url = 'https://open.weixin.qq.com/connect/oauth2/authorize?appid=wx3679b91facdc6b26&redirect_uri='.$redirect_uri.'&response_type=code&scope=snsapi_base&state=123#wechat_redirect';
            header("Location: $url");
        }elseif (strpos($_SERVER['HTTP_USER_AGENT'], 'Alipay')!==false){ //支付宝

        }else{
            returnFail('10060','请使用微信或支付宝扫码');
        }
    }

    /**
     * 车行人员记录
     * @param Request $request
     */
    public function fxgsVehiclePersonnelRecords(Request $request){
        $param = $request->post();

        $wechatUser = [
            'openid'=>$param['openid'],
            'username'=>$param['username'],
            'idcard'=>$param['idcard'],
            'phone'=>$param['phone'],
            'car_code'=>$param['car_code'] ? : '',
            'created_at'=>date('Y-m-d H:i:s')
        ];
        if(DB::table('wechat_user')->where('openid',$param['openid'])->where('is_deleted',0)->first()){//upd操作
            unset($wechatUser['created_at']);
            DB::table('wechat_user')->where('openid',$param['openid'])->update($wechatUser);
        }else{//添加操作
            DB::table('wechat_user')->insert($wechatUser);
        }

        //转发数据
        $device = new Device();
        $device->pushOrder('ws_client', json_encode($param, 320));

        returnSuccess();
    }

/****************************************************************肥西高速平安社区车闸****************************************************************************/
}
