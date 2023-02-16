<?php
declare(strict_types=1);


namespace Modules\Task\Controllers;

use Models\Mysql\RouterLogMysql;
use Wlsh\Router;

class LogController
{

    #[Router(method: 'TASK', auth: false)]
    public function IndexAction(array $data): void
    {
        if ($data['level'] === 'critica' or $data['level'] === 'alert' or $data['level'] === 'emergency') {
            sendEmail($data['content'], $data['info']);
        }

        if (APP_DEBUG) {
            monologByFile($data['content'], $data['info'], $data['channel'], $data['level']);
        }
    }

    /**
     * @throws Exception
     * @throws ProgramException
     * @throws \JsonException
     * @todo 流量日志如果需要进行分析，可以使用ClickHouse替换
     */
    #[Router(method: 'TASK', auth: false)]
    public function routerLogAction(array $data): void
    {
        RouterLogMysql::getInstance()->setLog($data);
        //RouterLogClickhouse::getInstance()->setLog($data);
    }

}