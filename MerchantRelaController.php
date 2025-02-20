<?php

namespace App\Http\Controllers;

use App\Models\Merchant;
use App\Models\MerchantRelation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

/**
 * 展示商户与业务商户关联关系管理
 */
class MerchantRelaController extends Controller
{
    public function index($id)
    {

        $data = [

            'title' => '商户管理',
            'pid'   => $id,
        ];

        return view('merchantrelation.datalist', $data);
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
        $pid   = $request->post('pid', 0);
        $where = [
            'pid' => $pid,
        ];

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

        $total      = MerchantRelation::where($where)->count();
        $total_page = ceil($total / $num);
        if ($page > $total_page) {
            return response()->json([
                "code" => 0,
                "data" => [],
            ]);
        }

        $start = ($page - 1) * $num;

        $list = MerchantRelation::where($where)->select('id', 'pid', 'mid', 'created_at')
            ->offset($start)
            ->limit($num)
            ->orderby('id', 'desc')
            ->get()
            ->toArray();
        $res = [];
        foreach ($list as $row) {

            $row['p_title'] = Merchant::where('id', $row['pid'])->value('title');

            $row['title'] = Merchant::where('id', $row['mid'])->value('title');
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
        $pid = $request->get('pid', 0);
        if ($pid == 0) {
            exit('参数缺失');
        }

        $data = ['pid' => $pid];
        return view('merchantrelation.create', $data);
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
            'mobile' => 'required',
            'pid'    => 'required',
            'mid'    => "required",
        ]);

        if ($validator->fails()) {
            show_msg('表单必填项不能为空', '10001');
        }

        $where = [
            'pid' => $data['pid'],
            'mid' => $data['mid'],
        ];
        $info = MerchantRelation::where($where)->first();
        if (!empty($info)) {
            show_msg('此商户已关联当前商户', '10002');
        }

        $ins_data = [
            'pid'        => $data['pid'],
            'mid'        => $data['mid'],
            'created_at' => date('Y-m-d H:i:s'),

        ];

        $aff_id = MerchantRelation::insertGetId($ins_data);

        if ($aff_id > 0) {
            show_msg('添加成功');
        } else {
            show_msg("添加失败", '10002');
        }
    }

    /**
     * [getRela 根据标题获取商户]
     * @Author   heizi
     * @DateTime 2022-04-20T13:27:32+0800
     * @param    Request                  $request [description]
     * @return   [type]                            [description]
     */
    public function getRela(Request $request)
    {

        $mobile = $request->post('mobile', '');

        if ($mobile == '') {
            show_msg('手机号不能为空', '10001');
        }

        $info = Merchant::where('mobile', $mobile)->first();

        if (empty($info)) {
            show_msg('商户不存在', '10002');
        }

        if ($info->type == 2) {
            show_msg('此商户为展示用户不可关联', '10003');
        }

        show_json($info);
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
            MerchantRelation::destroy($id_arr);

            DB::commit();
            show_msg("删除成功");
        } catch (Exception $e) {
            DB::rollback();
            show_msg('删除失败', '10003');
        }

    }
}
