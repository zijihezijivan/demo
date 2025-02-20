<?php

namespace App\Http\Controllers;

use App\Models\Merchant;
use App\Models\MerchantSystem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class MerchantController extends Controller
{
    public function index()
    {

        $data = [

            'title' => '商户管理',
        ];

        return view('merchant.datalist', $data);
    }

    /**
     * [getList 获取列表]
     * @Author   heizi
     * @DateTime 2022-04-19T15:09:55+0800
     * @param    Request                  $request [description]
     * @return   [type]                            [description]
     */
    public function getList(Request $request)
    {
        $where = [];

        $data = $request->post();
        if (isset($data['title']) && $data['title'] != '') {
            $where[] = ['title', 'like', $data['title'] . '%'];
        }
        if (isset($data['mobile']) && $data['mobile'] != '') {
            $where['mobile'] = $data['mobile'];
        }
        if (isset($data['username']) && $data['username'] != '') {
            $where['username'] = $data['username'];
        }

        $page = $request->post('page', 1);
        $num  = $request->post('limit', 1);

        $total      = Merchant::where($where)->count();
        $total_page = ceil($total / $num);
        if ($page > $total_page) {
            return response()->json([
                "code" => 0,
                "data" => [],
            ]);
        }

        $start = ($page - 1) * $num;

        $list = Merchant::where($where)->select('id', 'sn', 'title', 'mobile', 'username', 'status', 'type', 'created_at')
            ->offset($start)
            ->limit($num)
            ->orderby('id', 'desc')
            ->get()
            ->toArray();
        $res = [];
        foreach ($list as $row) {

            //获取设备管理面板
            $row['created_at'] = date('Y-m-d H:i:s', strtotime($row['created_at']));
            $res[]             = $row;
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
     * @param    Request                  $request [description]
     */
    public function create(Request $request)
    {

        return view('merchant.create');
    }
    /**
     * [addDo 添加操作]
     * @Author   heizi
     * @DateTime 2021-03-09T16:24:42+0800
     * @param    Request                  $request [description]
     */
    public function store(Request $request)
    {
        $data = $request->post();
        // show_msg('添加成功');
        $validator = Validator::make($data, [
            'title'    => 'required',
            'username' => 'required',
            'mobile'   => "required",
            'type'     => "required",
            'pwd'      => "required",
            'repwd'    => "required",
        ]);

        if ($validator->fails()) {
            show_msg('表单必填项不能为空', '10001');
        }

        if ($data['pwd'] != $data['repwd']) {
            show_msg('两次密码不一致', '10002');
        }

        $status = isset($data['status']) ? 1 : 0;

        $appid     = GetRandStr(16);
        $appSecret = GetRandStr(16);
        $sn        = GetRandStr(4);

        $ins_data = [
            'title'      => $data['title'],
            'username'   => $data['username'],
            'mobile'     => $data['mobile'],
            'address'    => $data['address'],
            'type'       => $data['type'],
            'status'     => $status,
            'passwd'     => md5($data['pwd']),
            'sn'         => $sn,
            'appid'      => $appid,
            'appsecret'  => $appSecret,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ];

        DB::beginTransaction();

        $aff_id = Merchant::insertGetId($ins_data);

        if ($aff_id > 0) {

            //更新数据
            $m_config = [
                'mid'           => $aff_id,
                'database_sign' => 0,
                'created_at'    => date('Y-m-d H:i:s'),
                'updated_at'    => date('Y-m-d H:i:s'),
            ];

            MerchantSystem::insert($m_config);

            DB::commit();
            show_msg('添加成功');
        } else {

            DB::rollback();
            show_msg("添加失败", '10002');
        }
    }
    /**
     * [editView 修改页面]
     * @Author   heizi
     * @DateTime 2021-03-09T16:24:45+0800
     * @param    Request                  $request [description]
     * @return   [type]                            [description]
     */
    public function edit(Request $request, $id)
    {

        $where = [
            'id' => $id,
        ];
        $info = Merchant::where($where)->first();

        $res = [
            'info' => $info->toArray(),
        ];
        // dd($info);
        return view('merchant.edit', $res);

    }

    /**
     * [editDo 修改操作]
     * @Author   heizi
     * @DateTime 2021-03-09T16:24:53+0800
     * @param    Request                  $request [description]
     * @return   [type]                            [description]
     */
    public function update(Request $request, $id)
    {
        $data = $request->post();
        // show_msg('添加成功');
        $validator = Validator::make($data, [
            'title'    => 'required',
            'username' => 'required',
            'mobile'   => "required",
            'type'     => "required",
        ]);

        if ($validator->fails()) {
            show_msg('表单必填项不能为空', '10001');
        }

        $info = Merchant::find($id);
        if (empty($info)) {
            show_msg('数据不存在');
        }

        $status = isset($data['status']) ? 1 : 0;

        $ins_data = [
            'title'      => $data['title'],
            'username'   => $data['username'],
            'mobile'     => $data['mobile'],
            'address'    => $data['address'],
            'type'       => $data['type'],
            'status'     => $status,
            'updated_at' => date('Y-m-d H:i:s'),
        ];

        if ($data['pwd'] != "" || $data['repwd'] != "") {
            if ($data['pwd'] != $data['repwd']) {
                show_msg('两次密码不一致', '10002');
            }
            $ins_data['passwd'] = md5($data['pwd']);
        }

        $aff_id = Merchant::where('id', $id)->update($ins_data);

        if ($aff_id > 0) {
            show_msg('修改成功');
        } else {
            show_msg("修改失败", '10002');
        }

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
        $info = Merchant::where($where)->first();

        $res = [
            'info' => $info->toArray(),
        ];

        return view('merchant.show', $res);

    }
    /**
     * [delDo 删除操作]
     * @Author   heizi
     * @DateTime 2021-03-09T16:25:01+0800
     * @param    Request                  $request [description]
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

            //删除商户  需要删除关联关系   设备关联
            Merchant::destroy($id_arr);

            DB::commit();
            show_msg("删除成功");
        } catch (Exception $e) {
            DB::rollback();
            show_msg('删除失败', '10003');
        }

    }
}
