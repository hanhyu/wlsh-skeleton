<?php
declare(strict_types=1);


namespace Models\Mysql;


use Wlsh\AbstractPdo;
use Wlsh\ProgramException;

class RouterLogMysql extends AbstractPdo
{
    protected string $table = 'router_log';

    public static function setDb(): string
    {
        return 'wlsh_log';
    }

    /**
     * @param array $data
     *
     * @return int
     */
    public function setLog(array $data): int
    {
        return self::getDb()->from($this->table)
            ->insert([
                'trace_id'   => $data['trace_id'],
                'level'      => $data['level'],
                'req_method' => $data['req_method'],
                'req_uri'    => $data['req_uri'],
                'req_data'   => json_encode($data['req_data'], JSON_THROW_ON_ERROR | 320),
                'req_ip'     => $data['req_ip'],
                'fd_time'    => $data['fd_time'],
                'req_time'   => $data['req_time'],
                'resp_time'  => $data['resp_time'],
                'resp_data'  => $data['resp_data'],
            ]);
    }

    /**
     * 路由日志列表
     *
     * User: hanhyu
     * Date: 2021/2/15
     * Time: 下午9:15
     *
     * @param array $data
     *
     * @return bool|array
     */
    public function getList(array $data): bool|array
    {
        return self::getDb()
            ->from($this->table)
            ->where($data['where'])
            ->orderBy('id', 'DESC')
            ->offset($data['curr_data'])
            ->limit($data['page_size'])
            ->select('id,trace_id,level,req_method,req_uri,req_ip,fd_time,req_time,resp_time,create_time')
            ->fetchAll();
    }

    public function getListCount(array $data): int
    {
        return self::getDb()->from($this->table)->where($data['where'])->select('COUNT(*)')->fetchColumn();
    }

    /**
     * User: hanhyu
     * Date: 2021/2/15
     * Time: 下午9:49
     *
     * @param string $trace_id
     *
     * @return array|bool
     */
    public function getInfoByTraceId(string $trace_id): array|false
    {
        return self::getDb()
            ->from($this->table)
            ->where('trace_id', $trace_id)
            ->select('trace_id, req_data, resp_data')
            ->fetchOne();
    }

}
