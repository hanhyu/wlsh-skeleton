<?php
declare(strict_types=1);


namespace Models\Mysql;

use Wlsh\AbstractPdo;

class SystemMenuMysql extends AbstractPdo
{
    protected string $table = 'frame_system_menu';

    public static function setDb(): string
    {
        return 'mysql';
    }

    public function getMenuList(array $data): array
    {
        $wheres = !empty($data['where']) ? $data['where'] : null;
        return self::getDb()->from($this->table)
            ->where($wheres)
            ->orderBy('id DESC')
            ->offset($data['curr_data'])
            ->limit($data['page_size'])
            ->fetchAll();
    }

    public function getListCount(): int
    {
        return self::getDb()->from($this->table)->count();
    }

}