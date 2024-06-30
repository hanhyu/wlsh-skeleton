<?php
declare(strict_types=1);


namespace Modules\System\Controllers;

use Wlsh\Router;

class ImController
{

    /**
     * ws请求内容:{"uri":"/system/im/getList"}
     * @param array $data
     * @return void
     * @throws \JsonException
     */
    #[Router(method: 'WS', auth: false)]
    public function getListAction(array $data): void
    {
        wsPush(wsJson(data: $data));
    }

}
