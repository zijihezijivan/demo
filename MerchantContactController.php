<?php

namespace App\Http\Controllers;

use App\Models\Merchant;
use App\Models\MerchantContact;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class MerchantContactController extends Controller
{
    public function index()
    {

        $data = [

            'title' => '异常通知联系方式管理',
        ];

        return view('merchantcontact.datalist', $data);
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
            // 'mid' => $request->_user_id,
        ];

        $data = $request->post();
        if (isset($data['username']) && $data['username'] != '') {
            $where['username'] = $data['username'];
        }
        if (isset($data['mobile']) && $data['mobile'] != '') {
            $where['mobile'] = $data['mobile'];
        }

        $page = $request->post('page', 1);
        $num  = $request->post('limit', 1);

        $total      = MerchantContact::where($where)->count();
        $total_page = ceil($total / $num);
        if ($page > $total_page) {
            return response()->json([
                "code" => 0,
                "data" => [],
            ]);
        }

        $start = ($page - 1) * $num;

        $list = MerchantContact::where($where)
            ->select('id', 'mid', 'username', 'mobile', 'job', 'updated_at', 'created_at')
            ->offset($start)
            ->limit($num)
            ->orderby('id', 'desc')
            ->get()
            ->toArray();
        $res = [];
        foreach ($list as $row) {

            $row['m_title'] = Merchant::where('id', $row['mid'])->value('title');
            // var_dump($row);
            $row['created_at'] = date('Y-m-d H:i:s', strtotime($row['created_at']));
            $row['updated_at'] = date('Y-m-d H:i:s', strtotime($row['updated_at']));
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

        $where = [
            'type' => 2,
        ];
        $m_list = Merchant::where($where)->select('id', 'title')->get()->toArray();

        $res = [
            'm_list' => $m_list,
        ];

        return view('merchantcontact.create', $res);
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
            'username' => 'required',
            'mobile'   => 'required',
            'job'      => 'required',
            'mid'      => "required",
        ]);

        if ($validator->fails()) {
            show_msg('表单必填项不能为空', '10001');
        }

        if (!is_mobile($data['mobile'])) {
            show_msg('手机号格式不正确', '10006');
        }

        if ($request->_user_type == 1) {
            show_msg('业务商户不能添加联系人', '10005');
        }

        $ins_data = [
            'username'   => $data['username'],
            'mobile'     => $data['mobile'],
            'job'        => $data['job'],
            'mid'        => $data['mid'],
            'updated_at' => date('Y-m-d H:i:s'),
            'created_at' => date('Y-m-d H:i:s'),
        ];

        DB::beginTransaction();

        try {

            MerchantContact::insert($ins_data);

            DB::commit();
            show_msg('添加成功');

        } catch (Exception $e) {

            DB::rollBack();
            show_msg('添加失败，请联系管理员', '10005');
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
            'type' => 2,
        ];
        $m_list = Merchant::where($where)->select('id', 'title')->get()->toArray();

        $where = [
            'id' => $id,
        ];
        $info = MerchantContact::where($where)->first();

        $res = [
            'info'   => $info->toArray(),
            'm_list' => $m_list,
        ];

        return view('merchantcontact.edit', $res);

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
            'username' => 'required',
            'mobile'   => 'required',
            'job'      => 'required',
            'mid'      => 'required',
        ]);

        if ($validator->fails()) {
            show_msg('表单必填项不能为空', '10001');
        }

        if (!is_mobile($data['mobile'])) {
            show_msg('手机号格式不正确', '10006');
        }

        $ins_data = [
            'username' => $data['username'],
            'mobile'   => $data['mobile'],
            'job'      => $data['job'],
            'mid'      => $data['mid'],
        ];

        $where = [
            'id' => $id,
        ];
        $aff_id = MerchantContact::where($where)->update($ins_data);
        if ($aff_id > 0) {
            show_msg('更新成功');
        } else {
            show_msg("更新失败", '10002');
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
            'type' => 1,
        ];
        $m_list = Merchant::where($where)->select('id', 'title')->get()->toArray();

        $where = [
            'id' => $id,
        ];
        $info = Devices::where($where)->first();

        $res = [
            'info'   => $info->toArray(),
            'd_list' => $m_list,
        ];

        return view('device.show', $res);

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

            MerchantContact::whereIn('id', $id_arr)->delete();
            //删除配置信息

            DB::commit();
            show_msg("删除成功");
        } catch (Exception $e) {
            DB::rollback();
            show_msg('删除失败', '10003');
        }

    }
}
