<?php

namespace App\Http\Controllers;

use App\Libs\RedisClient;
use App\Models\ParkingLog;
use Illuminate\Http\Request;

class IndexController extends Controller
{

    public $_pk_id = 16;
    //
    public function index()
    {

        return view('home.index');
    }
    public function ceshi()
    {
        //VfSSyic01QP0O3EH
        //SQ7LQ8miO9M0LJLh
        //jKTvSqWWtsnHpG7s

        echo randStr(16);die;
        // $shell = "/www/wwwroot/java/flyJar-1.0-SNAPSHOT.jar"

        // $zhenyun = new \App\Libs\Zhenyun();

        // $res = $zhenyun->getDeviceManage(['code' => '3e1517c1-3df4013b']);
        // dd($res);

        // $yingshi = new \App\Libs\Yingshi();

        // $data = [
        //     'token'    => 'at.c3ch79tvba8ducs2bhls5wgo17feijs7-968o5i70nt-0246y73-t6yshv4ul',
        //     'code'     => 'J35462124',
        //     'protocol' => 1,
        //     'name'     => '六安路我来测试',
        //     's_time'   => '2022-02-15 14:14:57',
        //     'e_time'   => '2022-02-15 15:14:57',
        // ];
        // // $bb = $yingshi->getAccessToken();
        // // $aa = $yingshi->getDeviceInfo($data);
        // // $aa = $yingshi->editDeviceName($data);
        // $aa = $yingshi->getVideoUrl($data);
        // dd($aa);

        // $qr = QrCode::size(300)->generate('https://www.baidu.com');
        // return $qr;
    }
    /**
     * [moni 模拟测试]
     * @Author   heizi
     * @DateTime 2021-08-12T09:19:25+0800
     * @return   [type]                   [description]
     */
    public function parking()
    {
        $color   = config('services.plate_color');
        $license = [
            '无牌车',
            '皖ADA6234',
            '皖ADT7568',
            '皖AD39829',
            '皖ADT5441',
            '皖ADD5962',
            '皖ADC8603',
            '皖AT363G',
            '皖A537W1',
        ];
        $device = [

            '111111' => '一号设备',
            '222222' => '二号设备',
            '333333' => '三号设备',
            '444444' => '四号设备',
            '555555' => '五号设备',
        ];

        $data = [
            'color'    => $color,
            'license'  => $license,
            'device'   => $device,
            'base_url' => env('APP_URL'),
        ];
        return view('home.car', $data);
    }

    /**
     * [addDo 车场日志]
     * @Author   heizi
     * @DateTime 2021-08-12T09:20:02+0800
     * @param    Request                  $request [description]
     */
    public function guards(Request $request)
    {
        $license = $request->post('license', '');
        $color   = $request->post('color', 1);
        $code    = $request->post('code', '999999');

        $json = file_get_contents('../guard.txt');

        $json_data = json_decode($json, true);

        $json_data['AlarmInfoPlate']['result']['PlateResult']['license']   = $license;
        $json_data['AlarmInfoPlate']['result']['PlateResult']['colorType'] = $color;
        $json_data['AlarmInfoPlate']['serialno']                           = $code;

        $json_data['AlarmInfoPlate']['result']['PlateResult']['timeStamp']['Timeval']['sec'] = time();

        //数据发送
        // print_r($json_data);
        $admin_api_url = env('ADMIN_API_URL');
        $url           = $admin_api_url . "/api/v1/parkinglog/guardsendlog";
        $res           = $this->curl_post($url, $json_data);

        return $res;
    }
    /**
     * [sendlog 车位日志]
     * @Author   heizi
     * @DateTime 2021-08-12T13:59:46+0800
     * @param    Request                  $request [description]
     * @return   [type]                            [description]
     */
    public function sendlog(Request $request)
    {

        $license   = $request->post('license', '皖A537W1');
        $color     = $request->post('color', 1);
        $code      = $request->post('code', '111111');
        $zone_name = $request->post('zone_name', 1);
        $status    = $request->post('status', 0);

        $json = file_get_contents('../car.txt');

        $data = json_decode($json, true);
        // $data['']
        // $data = json_decode($data, true);
        $data['product_h']['plate']['plate']           = base64_encode($license);
        $data['product_h']['parking']['zone_name']     = base64_encode('YB-00' . $zone_name);
        $data['product_h']['parking']['zone_id']       = $zone_name - 1;
        $data['product_h']['parking']['parking_state'] = $status;
        $data['product_h']['plate']['color']           = $color;
        $data['product_h']['reco']['reco_time']        = date('Y-m-d H:i:s');
        $data['device_info']['sn']                     = $code;

        $admin_api_url = env('ADMIN_API_URL');
        $url           = $admin_api_url . "/api/v1/parkinglog/sendlog";
        $res           = $this->curl_post($url, $data);

        return $res;
        // print_r($res);
    }

    public function getGuardList()
    {

        $config = [
            'host'  => '127.0.0.1',
            'port'  => 6379,
            'db_id' => 0,

        ];
        $redis = new RedisClient($config);

        $list_name = 'guard_jihe_' . $this->_pk_id;
        $list      = $redis->sMembers($list_name);

        $str = "";
        if (!empty($list)) {

            foreach ($list as $val) {
                $str .= '<button class="layui-btn">' . $val . '</button>';
            }
        }

        $res = [
            'code' => 1,
            'list' => $str,
        ];

        echo json_encode($res);die;
    }

    public function loglist()
    {

        $where = [
            'pk_id'  => $this->_pk_id,
            'status' => 1,
        ];

        $list = ParkingLog::where($where)->select('id', 'ps_id', 'license')->get()->toArray();

        $str = '';
        if (!empty($list)) {

            foreach ($list as $row) {

                $ps_name = ParkingLog::find($row['id'])->parkingspace()->first()->title;
                $d_name  = ParkingLog::find($row['id'])->devices()->first()->name;
                $str .= '<button class="layui-btn layui-btn-danger">' . $d_name . '--' . $ps_name . '--' . $row['license'] . '</button>';
            }
        }
        $res = [
            'code' => 1,
            'list' => $str,
        ];

        echo json_encode($res);die;
    }

    public function curl_post($url, $data = array())
    {

        $data_string = json_encode($data);
        $ch          = curl_init();

        curl_setopt($ch, CURLOPT_URL, $url);

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);

        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

        // POST数据

        curl_setopt($ch, CURLOPT_POST, 1);

        // 把post的变量加上

        curl_setopt($ch, CURLOPT_POSTFIELDS, $data_string);

        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'Content-Type: application/json',
            'Content-Length: ' . strlen($data_string))
        );
        $output = curl_exec($ch);

        curl_close($ch);

        return $output;

    }
}
