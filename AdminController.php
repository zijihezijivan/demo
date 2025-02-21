<?php

namespace App\Http\Controllers;

use App\Models\AdminUser;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class AdminController extends Controller
{
    //ceshi
    public function index(Request $request)
    {
        // dump($request->session()->get('userInfo'));
        return view('home.index');
    }

    public function main(Request $request)
    {

        $data = [

            'l_num'   => 1,
            'd_num'   => 2,
            'sp_num'  => 3,
            'log_num' => 4,
        ];

        return view('home.main', $data);
    }

    public function editpasswd(Request $request)
    {

        $data = $request->post();

        $validator = Validator::make($data, [

            'oldpwd' => "required|min:6|max:16",
            'pwd'    => "required|min:6|max:16",
            'repwd'  => "required|min:6|max:16",
        ]);

        if ($validator->fails()) {
            show_msg('表单为空或密码长度超出限制', '10001');
        }

        $user = AdminUser::find($request->_user_id);
        // echo Hash::make($data['oldpwd']);die;
        if (!Hash::check($data['oldpwd'], $user->password)) {
            show_json('原密码不匹配', '10002');
        }

        $pwd   = $request->post('pwd');
        $repwd = $request->post('repwd');

        if ($pwd != $repwd) {
            show_json('两次密码不匹配', '10002');
        }

        $newpwd = Hash::make($pwd);

        $data = [
            'password' => $newpwd,
        ];

        $aff_id = AdminUser::where('id', $request->_user_id)->update($data);

        if ($aff_id > 0) {
            show_json('修改成功', '10000');
        } else {
            show_json('修改失败，请联系管理员', '10003');
        }

    }
    /**
     * [passwd 修改用户密码]
     * @Author   heizi
     * @DateTime 2021-03-17T09:33:03+0800
     * @return   [type]                   [description]
     */
    public function passwd()
    {

        return view('user.password');
    }
}
