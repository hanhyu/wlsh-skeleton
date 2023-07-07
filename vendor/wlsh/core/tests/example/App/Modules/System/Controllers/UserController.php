<?php
declare(strict_types=1);

namespace Modules\System\Controllers;

use Domain\System\UserDomain;
use Models\Forms\SystemUserForms;
use Models\Redis\UserRedis;
use Swoole\Coroutine;
use Swoole\Http\Request;
use Swoole\Http\Response;
use Wlsh\Router;

class UserController
{

    protected UserDomain $user;

    public function __construct()
    {
        $this->user = new UserDomain();
    }

    /**
     * @param Request $request
     * @param Response $response
     */
    #[Router(method: 'GET', auth: false)]
    public function getUserListAction(Request $request, Response $response): void
    {
        $data = validator($request, SystemUserForms::$getUserList);
        $res  = $this->user->getInfoList($data);
        $res  = httpJson(data: $res);
        routerLog($request, 'info', $res);
        $response->end($res);
    }

    /**
     * 根据id获取用户信息
     */
    #[Router(method: 'GET', auth: false)]
    public function getUserAction(Request $request, Response $response): void
    {
        $data = validator($request, SystemUserForms::$getUser);
        $res  = $this->user->getUserById((int)$data['id']);
        if (!empty($res)) {
            $response->end(httpJson(data: $res));
        } else {
            $response->end(httpJson(500, '查询失败'));
        }
    }

    #[Router(method: 'GET', auth: false)]
    public function getTokenAction(Request $request, Response $response): void
    {
        $token = UserRedis::getInstance()->getKey($request->get['token']);
        $response->end(httpJson(data: ['token' => $token]));
    }

    #[Router(method: 'POST', auth: false)]
    public function loginAction(Request $request, Response $response): void
    {
        $data = validator($request, SystemUserForms::$userLogin);
        $info = $this->user->getInfoByName($data['name']);
        if (!empty($info)) {
            if ($info['status'] === 0) {
                $resp_content = httpJson(400, '该用户处于禁用状态');
            } else if (password_verify($data['pwd'], $info['pwd'])) {
                $params['id']   = $info['id'];
                $params['name'] = $info['name'];
                $params['time'] = time();
                $token          = tokenEncode($params);
                //$this->response->cookie('token', $token);
                $resp_content = httpJson(data: ['token' => $token]);

                $params['ip'] = ip2long($request->header['x-real-ip'] ?? getIP($request->server));
                //$this->user->setLoginLog($params);
                //模拟日志发送邮件
                //task_monolog($this->server, $data['name'], '用户登录:', 'alert');
            } else {
                $resp_content = httpJson(400, '用户名或密码错误');
            }
        } else {
            $resp_content = httpJson(400, '用户名或密码错误');
        }
       // sign(Coroutine::getCid(), $resp_content);

        $response->end($resp_content);
    }

}
