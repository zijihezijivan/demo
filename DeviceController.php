<?php

namespace App\Http\Controllers;

use App\Models\Devices;
use App\Models\Merchant;fdf
use App\Models\ParkingLot;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use QrCode;

/**
 * 设备管理
 */
class DeviceController extends Controller
{
    public function addDevice(Request $request)
    {
        $param = $request->post();
        $now   = date('Y-m-d H:i:s');

        if (DB::table('devices')->where('code', $param['sn'])->first()) {
            //update
            DB::table('devices')->where('code', $param['sn'])->update([
                'status'      => 1,
                'type'        => $param['type'],
                'online_time' => $now,
            ]);
        } else {
            //add
            $data = [
                'code'        => $param['sn'],
                'type'        => $param['type'],
                'online_time' => $now,
                'created_at'  => $now,
            ];
            DB::table('devices')->insert($data);
        }
    }

    public function closeDevice(Request $request)
    {
        $param = $request->post();
        DB::table('devices')->where('code', $param['sn'])->update([
            'status'     => 2,
            'close_time' => date('Y-m-d H:i:s'),
        ]);
    }

    public function index()
    {

        $data = [

            'title' => '设备管理',
        ];

        return view('device.datalist', $data);
    }

    /**
     * [index 闸机管理列表]
     * @Author   heizi
     * @DateTime 2021-09-06T15:14:37+0800
     * @return   [type]                   [description]
     */
    public function doorDevice()
    {

        $data = [

            'title' => '车闸设备管理',
        ];

        return view('device.doordatalist', $data);
    }

    /**
     * [getList description]
     * @Author   heizi
     * @DateTime 2021-03-09T14:45:28+0800
     * @return   [type]                   [description]
     */
    public function getList(Request $request)
    {

        $where = [

        ];

        $data = $request->post();
        if (isset($data['code']) && $data['code'] != '') {
            $where['code'] = $data['code'];
        }
        if (isset($data['status']) && $data['status'] != '') {
            $where['status'] = $data['status'];
        }
        if (isset($data['pk_sn']) && $data['pk_sn'] != '') {
            $condition = [
                'mid' => $request->_user_id,
                'sn'  => $data['pk_sn'],
            ];
            $info      = ParkingLot::where($condition)->first();
            if ($info) {
                $where['pk_id'] = $info->id;
            } else {
                $where['pk_id'] = 0;
            }
        }

        $page = $request->post('page', 1);
        $num  = $request->post('limit', 1);

        $total      = Devices::where($where)->whereNotIn('type', [3])->count();
        $total_page = ceil($total / $num);
        if ($page > $total_page) {
            return response()->json([
                "code" => 0,
                "data" => [],
            ]);
        }

        $start = ($page - 1) * $num;

        $list = Devices::where($where)
            ->whereNotIn('type', [3])
            ->select('id', 'mid', 'code', 'name', 'status', 'bind_time', 'online_time', 'close_time')
            ->offset($start)
            ->limit($num)
            ->orderby('id', 'desc')
            ->get()
            ->toArray();
        $res  = [];
        foreach ($list as $row) {
            $row['m_title'] = Merchant::where('id', $row['mid'])->value('title');
            // var_dump($row);
            $res[] = $row;
        }
        return response()->json([
            "code"  => 0,
            "msg"   => "数据为空",
            "count" => $total,
            "data"  => $res,
        ]);
    }

    /**
     * [getList 获取闸机设备列表]
     * @Author   heizi
     * @DateTime 2021-03-09T14:45:28+0800
     * @return   [type]                   [description]
     */
    public function getDoorList(Request $request)
    {

        $where = [
        ];

        $data = $request->post();
        if (isset($data['code']) && $data['code'] != '') {
            $where['code'] = $data['code'];
        }
        if (isset($data['status']) && $data['status'] != '') {
            $where['status'] = $data['status'];
        }
        if (isset($data['pk_sn']) && $data['pk_sn'] != '') {
            $condition = [
                'mid' => $request->_user_id,
                'sn'  => $data['pk_sn'],
            ];
            $info      = ParkingLot::where($condition)->first();
            if ($info) {
                $where['pk_id'] = $info->id;
            } else {
                $where['pk_id'] = 0;
            }
        }

        $page = $request->post('page', 1);
        $num  = $request->post('limit', 1);

        $total      = Devices::where($where)->whereIn('type', [3])->count();
        $total_page = ceil($total / $num);
        if ($page > $total_page) {
            return response()->json([
                "code" => 0,
                "data" => [],
            ]);
        }

        $start = ($page - 1) * $num;

        $list = Devices::where($where)
            ->whereIn('type', [3])
            ->select('id', 'code', 'name', 'mid', 'address', 'status', 'mode','hs_time', 'bind_time', 'online_time', 'close_time')
            ->offset($start)
            ->limit($num)
            ->orderby('id', 'desc')
            ->get()
            ->toArray();
        $res  = [];
        foreach ($list as $row) {

            $row['m_title'] = Merchant::where('id', $row['mid'])->value('title');
            //获取设备管理面板
            $param = [
                'code' => $row['code'],
            ];
            // var_dump($row);
            $res[] = $row;
        }
        return response()->json([
            "code"  => 0,
            "msg"   => "数据为空",
            "count" => $total,
            "data"  => $res,
        ]);
    }

    /**
     * [addView 添加页面]
     * @Author   heizi
     * @DateTime 2021-03-09T16:24:39+0800
     * @param Request $request [description]
     */
    public function create(Request $request)
    {
        $where  = [
            'type' => 1,
        ];
        $d_list = Merchant::where($where)->select('id', 'title')->get()->toArray();

        $res = [
            'd_list' => $d_list,
        ];

        return view('device.create', $res);
    }

    /**
     * [addDo 添加操作]
     * @Author   heizi
     * @DateTime 2021-03-09T16:24:42+0800
     * @param Request $request [description]
     */
    public function store(Request $request)
    {
        $data = $request->post();
        // show_msg('添加成功');
        $validator = Validator::make($data, [
            'code' => 'required',
            'name' => 'required',
            'mid'  => "required",
        ]);

        if ($validator->fails()) {
            show_msg('表单必填项不能为空', '10001');
        }

        $where  = [
            'code'   => $data['code'],
            'status' => 1,
            'mid'    => 0,
        ];
        $d_info = Devices::where($where)->first();

        if (!$d_info) {
            show_msg('设备不在线或不存在', '10002');
        }

        if ($d_info->sign == 0) {
            show_msg('设备被禁用，禁止绑定', '10003');
        }

        $ins_data = [
            'name'       => $data['name'],
            'mid'        => $data['mid'],
            'bind_time'  => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
            'address'    => $data['address'],
        ];

        DB::beginTransaction();

        try {

            Devices::where('id', $d_info['id'])->update($ins_data);

            DB::commit();
            show_msg('设备绑定成功');

        } catch (Exception $e) {

            DB::rollBack();
            show_msg('绑定设备失败，请联系管理员', '10005');
        }

    }

    /**
     * [editView 修改页面]
     * @Author   heizi
     * @DateTime 2021-03-09T16:24:45+0800
     * @param Request $request [description]
     * @return   [type]                            [description]
     */
    public function edit(Request $request, $id)
    {

        $where  = [
            'type' => 1,
        ];
        $m_list = Merchant::where($where)->select('id', 'title')->get()->toArray();

        $where = [
            'id' => $id,
        ];
        $info  = Devices::where($where)->first();

        $door_mode = config('services.door_device_mode');

        $door_mode_sign = 0;
        if ($info->type == 3) {
            $door_mode_sign = 1;
        }
        $res = [
            'info'           => $info->toArray(),
            'd_list'         => $m_list,
            'door_mode'      => $door_mode,
            'door_mode_sign' => $door_mode_sign,
        ];

        return view('device.edit', $res);

    }

    /**
     * [editDo 修改操作]
     * @Author   heizi
     * @DateTime 2021-03-09T16:24:53+0800
     * @param Request $request [description]
     * @return   [type]                            [description]
     */
    public function update(Request $request, $id)
    {
        $data = $request->post();
        // show_msg('添加成功');
        $validator = Validator::make($data, [
            'name' => 'required',
            'mid'  => 'required',
        ]);

        if ($validator->fails()) {
            show_msg('表单必填项不能为空', '10001');
        }

        $ins_data = [
            'name'    => $data['name'],
            'mid'     => $data['mid'],
            'address' => $data['address'],
            'mode'    => $data['mode'],
            'hs_time' => $data['hs_time'],

        ];
        $this->car_device_mode($id, $data);
        $where  = [
            'id' => $id,
        ];
        $aff_id = Devices::where($where)->update($ins_data);
        if ($aff_id > 0) {
            show_msg('更新成功');
        } else {
            show_msg("更新失败", '10002');
        }

    }

    /**
     * 设置车闸盒子开门模式
     * author heizi
     * time 2022/10/21
     * @param $id
     * @param $data
     */
    public function car_device_mode($id, $data)
    {

        $where = [
            'id' => $id,
        ];
        $info  = Devices::where($where)->first();
        if (empty($info)) {
            return false;
        }

        if (!isset($data['mode'])) {
            return false;
        }
        if ($data['mode'] == '') {
            return false;
        }


        if ($info->mode == $data['mode']) {
            return false;
        }
        //下发命令

        $wsclient = new \App\Libs\Device();

        $command = [
            "id"     => 12,
            "method" => "configCentre.setConfig",
            "params" => [
                "name"    => "DoorTrustStrategy",
                "content" => [
                    "TrustMode" => (int)$data['mode'],
                ],
            ],
        ];

        $wsclient->pushOrder($info->code, enJson($command));
        return true;
    }

    /**
     * [show 详情]
     * @Author   heizi
     * @DateTime 2021-03-09T16:24:56+0800
     * @param Request $request [description]
     * @return   [type]                            [description]
     */
    public function show(Request $request, $id)
    {

        $where  = [
            'type' => 1,
        ];
        $m_list = Merchant::where($where)->select('id', 'title')->get()->toArray();

        $where = [
            'id' => $id,
        ];
        $info  = Devices::where($where)->first();

        $res = [
            'info'   => $info->toArray(),
            'd_list' => $m_list,
        ];

        return view('device.show', $res);

    }

    /**
     * [editView 闸机修改页面]
     * @Author   heizi
     * @DateTime 2021-03-09T16:24:45+0800
     * @param Request $request [description]
     * @return   [type]                            [description]
     */
    public function guardedit(Request $request, $id)
    {

        $where = [
            'id' => $id,
        ];
        $info  = Devices::where($where)->first();

        $res = [
            'info' => $info->toArray(),
        ];
        // dd($info);
        return view('device.guardedit', $res);

    }

    /**
     * [delDo 删除操作]
     * @Author   heizi
     * @DateTime 2021-03-09T16:25:01+0800
     * @param Request $request [description]
     * @return   [type]                            [description]
     */
    public function delDo(Request $request)
    {

        $id_arr = $request->post('ids');

        if (empty($id_arr)) {
            show_msg('请选择要删除的数据', '10001');
        }

        //开启事务
        DB::beginTransaction();
        try {

            // Devices::destroy($id_arr);
            //删除绑定信息
            $upt_data = [
                'name'       => '',
                'mid'        => 0,
                'address'    => '',
                'bind_time'  => null,
                'updated_at' => date('Y-m-d H:i:s'),
            ];
            Devices::whereIn('id', $id_arr)->update($upt_data);
            //删除配置信息

            DB::commit();
            show_msg("删除成功");
        } catch (Exception $e) {
            DB::rollback();
            show_msg('删除失败', '10003');
        }

    }

    public function qrcode(Request $request, $id)
    {
        $where = [
            'id' => $id,
        ];
        $info  = Devices::where($where)->first();

        $param = [
            'mid'         => $info->id,
            'device_code' => $info->code,
        ];

        $get_data = http_build_query($param);

        $qr_url = 'http://zycadmin.hongdaosz.com/api/v1/car/qrCode?' . $get_data;
        // dd($qr_url);
        // $qr_url = urlencode($qr_url);
        // dd(urlencode("&"));
        $img_url = $qr_url;
        $qr_url  = QrCode::size(300)->generate($qr_url);

        $res = [
            'qr_url'  => $qr_url,
            'img_url' => $img_url,
        ];
        return view('device.qrcode', $res);
    }

}
