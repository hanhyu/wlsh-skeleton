<?php
declare(strict_types=1);

namespace Modules\System\Controllers;

use Domain\System\UserDomain;
use Models\Forms\SystemUserForms;
use Models\Redis\UserRedis;
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
        $res = $this->user->getInfoList($data);
        $res = httpJson(data: $res);
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
        $res = $this->user->getUserById((int)$data['id']);
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

}
