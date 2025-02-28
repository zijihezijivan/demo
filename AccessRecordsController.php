<?php
fdsfsd
namespace App\Http\Controllers;
fdsffsdfsfdsfsfds
use App\Models\AccessRecords;
use App\Models\Devices;
use App\Models\Merchant;
use Illuminate\Http\Request;

class AccessRecordsController extends Controller
{
    public function index(Request $request)
    {

        $nucleic_acid_result_arr = config('services.nucleic_acid_result');
        $vaccination_info_arr    = config('services.vaccination_info');
        $travel_code_arr         = config('services.travel_code_arr');
        $ankang_code_arr         = config('services.ankang_code_arr');

        $data = [

            'title'                   => '日志管理',
            'ankang_code_arr'         => $ankang_code_arr,
            'travel_code_arr'         => $travel_code_arr,
            'vaccination_info_arr'    => $vaccination_info_arr,
            'nucleic_acid_result_arr' => $nucleic_acid_result_arr,
        ];

        return view('accessrecords.datalist', $data);
    }

    /**
     * [getList description]
     * @Author   heizi
     * @DateTime 2021-03-09T14:45:28+0800
     * @return   [type]                   [description]
     */
    public function getList(Request $request)
    {

        //判断商户
        $user_type = $request->_user_type;

        $where = [];

        $data = $request->post();
        if (isset($data['ankang_code']) && $data['ankang_code'] != '') {
            $where['ankang_code'] = $data['ankang_code'];
        }

        if (isset($data['travel_code']) && $data['travel_code'] != '') {
            $where['travel_code'] = $data['travel_code'];
        }

        if (isset($data['nucleic_acid_result']) && $data['nucleic_acid_result'] != '') {
            $where['nucleic_acid_result'] = $data['nucleic_acid_result'];
        }

        if (isset($data['vaccination_info']) && $data['vaccination_info'] != '') {
            $where['vaccination_info'] = $data['vaccination_info'];
        }

        $page = $request->post('page', 1);
        $num  = $request->post('limit', 1);

        $total      = AccessRecords::where($where)->count();
        $total_page = ceil($total / $num);
        if ($page > $total_page) {
            return response()->json([
                "code" => 0,
                "data" => [],
            ]);
        }

        $start = ($page - 1) * $num;

        $list = AccessRecords::where($where)
            ->select('id', 'mid', 'sex', 'phone', 'device_code', 'username', 'idcard', 'nucleic_acid_result', 'vaccination_info', 'ankang_code', 'temperature', 'check_point', 'created_at')
            ->offset($start)
            ->limit($num)
            ->orderby('id', 'desc')
            ->get()
            ->toArray();
        $res = [];
        if (!empty($list)) {

            foreach ($list as $row) {
                $m_info = Merchant::where('id', $row['mid'])->first();
                if ($m_info) {
                    $row['m_title']    = $m_info->title;
                    $row['xq_address'] = $m_info->address;
                } else {
                    $row['m_title']    = '未知';
                    $row['xq_address'] = '未知';
                }

                $row['address']    = Devices::where('code', $row['device_code'])->value('address');
                $row['created_at'] = date('Y-m-d H:i:s', strtotime($row['created_at']));
                // var_dump($row);
                $res[] = $row;
            }
        }

        return response()->json([
            "code"  => 0,
            "msg"   => "数据为空",
            "count" => $total,
            "data"  => $res,
        ]);
    }

    /**
     * [show 详情]
     * @Author   heizi
     * @DateTime 2021-03-09T16:24:56+0800
     * @param    Request                  $request [description]
     * @return   [type]                            [description]
     */
    public function show(Request $request, $id)
    {

        $where = [
            'id' => $id,
        ];
        $info = AccessRecords::where($where)->first();

        switch ($info->sex) {
            case '0':
                $info->sex = "女";
                break;
            case '1':
                $info->sex = "男";
                break;

            default:
                $info->sex = "未知";
                break;
        }

        $nucleic_acid_result_arr = config('services.nucleic_acid_result');
        $vaccination_info_arr    = config('services.vaccination_info');
        $travel_code_arr         = config('services.travel_code_arr');
        $ankang_code_arr         = config('services.ankang_code_arr');

        if (!isset($nucleic_acid_result_arr[$info->nucleic_acid_result])) {
            $info->nucleic_acid_result = '未知';
        } else {
            $info->nucleic_acid_result = $nucleic_acid_result_arr[$info->nucleic_acid_result];
        }

        if (!isset($vaccination_info_arr[$info->vaccination_info])) {
            $info->vaccination_info = '未知';
        } else {
            $info->vaccination_info = $vaccination_info_arr[$info->vaccination_info];
        }

        if (!isset($travel_code_arr[$info->travel_code])) {
            $info->travel_code = '未知';
        } else {
            $info->travel_code = $travel_code_arr[$info->travel_code];
        }

        if (!isset($ankang_code_arr[$info->ankang_code])) {
            $info->ankang_code = '未知';
        } else {
            $info->ankang_code = $ankang_code_arr[$info->ankang_code];
        }

        $info->created_at = date('Y-m-d H:i:s', strtotime($info->created_at));

        //查询小区位置
        $m_info = Merchant::where('id', $info->mid)->first();
        if ($m_info) {
            $info->m_title    = $m_info->title;
            $info->xq_address = $m_info->address;
        } else {
            $info->m_title    = '未知';
            $info->xq_address = '未知';
        }

        //获取设备位置
        $d_info = Devices::where('code', $info->device_code)->first();
        if ($d_info) {
            $info->address = $d_info->address;
        } else {
            $info->address = '未知';
        }

        $res = [
            'info' => $info->toArray(),
        ];

        // dd($info);
        return view('accessrecords.show', $res);

    }
}
