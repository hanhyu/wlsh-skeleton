<?php
declare(strict_types=1);

namespace Modules\Api\Controllers;


use Swoole\Http\Request;
use Swoole\Http\Response;
use Wlsh\Router;

class ExampleController
{
    #[Router(method: 'GET', auth: false)]
    public function getListAction(Request $request, Response $response): void
    {
        $response->end('hello world');
    }

    #[Router(method: 'GET', auth: false)]
    public function getInfoAction(Request $request, Response $response): void
    {
        $response->end(httpJson(data: ['a' => 123, 'b' => 345]));
    }

}