<?php

namespace App\Http\Controllers;

use App\Models\AdminUser;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class LoginController extends Controller
{

    public function login()
    {
        return view('user.login');
    }

    /**
     * [loginIn 登陆操作]
     * @Author   heizi
     * @DateTime 2021-03-09T10:41:37+0800
     * @param    Request                  $request [description]
     * @return   [type]                            [description]
     */
    public function loginIn(Request $request)
    {
        $data = $request->post();

        $validator = Validator::make($data, [
            'username' => 'required|max:13',
            'password' => 'required',
            'vercode'  => "required|numeric|captcha",
        ]);

        if ($validator->fails()) {
            show_msg('验证码不正确或表单值为空', '10001');
        }

        //数据处理
        $username = $request->post('username');
        $passwd   = $request->post('password');

        $info = AdminUser::where('username', $username)->first();

        if (!$info) {
            show_msg('用户不存在或密码错误', '10002');
        }

        // echo Hash::make('admin123');die;
        if (!Hash::check($passwd, $info->password)) {
            show_msg('用户不存在或密码错误', '10003');
        }
        //数据放入到session
        $data = [
            'user_id'  => $info->id,
            'username' => $info->username,
            // 'mobile'   => $info->mobile,
        ];

        $request->session()->put('userInfo', $data);
        $request->session()->save();

        show_msg('登陆成功');

    }

    public function loginOut(Request $request)
    {

        $request->session()->flush();

        return redirect(url('/'));

    }
    /**
     * [captcha 获取验证码]
     * @Author   heizi
     * @DateTime 2021-03-09T10:07:46+0800
     * @return   [type]                   [description]
     */
    public function captcha()
    {
        return captcha('math');
    }
}
