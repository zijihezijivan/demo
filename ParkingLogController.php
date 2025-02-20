<?php

namespace App\Http\Controllers;

use App\Models\Devices;
use App\Models\ParkingLog;
use App\Models\ParkingLot;
use App\Models\ParkingSpace;
use Illuminate\Http\Request;

class ParkingLogController extends Controller
{
    public function index()
    {

        $data = [

            'title' => '车场管理',
        ];

        return view('parkinglog.datalist', $data);
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
            'mid' => $request->_user_id,
        ];
        $data = $request->post();
        if (isset($data['license']) && $data['license'] != '') {
            $where['license'] = $data['license'];
        }
        if (isset($data['status']) && $data['status'] != '') {
            $where['status'] = $data['status'];
        }
        if (isset($data['pk_sn']) && $data['pk_sn'] != '') {
            $condition = [
                'mid' => $request->_user_id,
                'sn'  => $data['pk_sn'],
            ];
            $info = ParkingLot::where($condition)->first();
            if ($info) {
                $where['pk_id'] = $info->id;
            }
        }
        if (isset($data['d_sn']) && $data['d_sn'] != '') {
            $condition = [
                'mid' => $request->_user_id,
                'sn'  => $data['d_sn'],
            ];
            $info = Devices::where($condition)->first();
            if ($info) {
                $where['d_id'] = $info->id;
            }
        }
        if (isset($data['ps_sn']) && $data['ps_sn'] != '') {
            $condition = [
                'mid' => $request->_user_id,
                'sn'  => $data['ps_sn'],
            ];
            $info = ParkingSpace::where($condition)->first();
            if ($info) {
                $where['ps_id'] = $info->id;
            }
        }

        $page = $request->post('page', 1);
        $num  = $request->post('limit', 1);

        $total      = ParkingLog::where($where)->count();
        $total_page = ceil($total / $num);
        if ($page > $total_page) {
            return response()->json([
                "code" => 0,
                "data" => [],
            ]);
        }

        $start = ($page - 1) * $num;

        $list = ParkingLog::where($where)
            ->select('id', 'license', 'color', 'car_type', 's_time', 'e_time', 'stay_time', 'charge_time', 'status', 'big_img', 'pk_id', 'd_id', 'ps_id')
            ->offset($start)
            ->limit($num)
            ->orderby('id', 'desc')
            ->get()
            ->toArray();

        $plate_type  = config('services.plate_type');
        $plate_color = config('services.plate_color');

        $res = [];
        foreach ($list as $row) {

            $row['lot_name'] = ParkingLot::find($row['pk_id'])->first()->title;
            $row['d_name']   = Devices::find($row['d_id'])->first()->name;
            $row['sp_name']  = ParkingSpace::find($row['ps_id'])->first()->title;

            $row['s_time'] = date('Y-m-d H:i:s', $row['s_time']);
            if ($row['e_time'] == 0) {
                $row['e_time'] = '';
            } else {
                $row['e_time'] = z('Y-m-d H:i:s', $row['e_time']);
            }

            if ($row['license'] == '') {
                $row['license'] = '无牌车';
            }
            $row['stay_time'] = sec2Time($row['stay_time']);
            // $row['color']    = isset($plate_color[$row['color']]) ? $plate_color[$row['color']] : '未知';
            // $row['car_type'] = isset($plate_type[$row['car_type']]) ? $plate_type[$row['car_type']] : '未知';
            $res[] = $row;
        }
        return response()->json([
            "code"  => 0,
            "msg"   => "数据为空",
            "count" => $total,
            "data"  => $res,
        ]);
    }
}
