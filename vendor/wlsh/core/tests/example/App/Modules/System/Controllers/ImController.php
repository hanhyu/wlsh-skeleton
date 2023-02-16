<?php
declare(strict_types=1);


namespace Modules\System\Controllers;

use Wlsh\Router;

class ImController
{

    #[Router(method: 'WS', auth: false)]
    public function getListAction(array $data): void
    {
        wsPush(wsJson(data: $data));
    }

}