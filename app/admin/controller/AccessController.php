<?php
namespace app\admin\controller;

use app\common\Auth;
use app\admin\model\Manager;

class AccessController extends BaseController
{
    protected $middleware = [];

    public function createTokenByPassword()
    {
        $post = $this->request->post();

        $this->validate($post, 'Access.password');

        $map = [
            ['username', '=', $post['username']],
            ['password', '=', md5($post['password'])]
        ];

        $manager = Manager::where($map)->find();

        if (empty($manager)) {
            $this->error(401, '无权限登录或已禁用', 'LOGIN_FAIL');
        }

        if (!$manager->status) {
            $this->error(403, '该账号已被禁用', 'NOT_AUTH');
        }

        $accessToken = Auth::setAccessToken($manager->id, $manager);
        $this->app->event->trigger('ManagerLoginAfter', $manager);

        $this->success(201, $accessToken);
    }

    public function createTokenByWechat()
    {
        $param = $this->request->param();

        $this->validate($param, 'Access.wechat');

        $oauth = \thirdconnect\Oauth::wechat();
        $info = $oauth->getUserinfo();

        $manager = Manager::where('openid', $info['openid'])->find();

        if (empty($manager)) {
            $this->error(401, '无权限登录或已禁用', 'LOGIN_FAIL');
        }

        if (!$manager->status) {
            $this->error(403, '该账号已被禁用', 'NOT_AUTH');
        }

        $accessToken = Auth::setAccessToken($manager->id, $manager);
        $this->app->event->trigger('ManagerLoginAfter', $manager);

        $this->success(201, $accessToken);
    }
}