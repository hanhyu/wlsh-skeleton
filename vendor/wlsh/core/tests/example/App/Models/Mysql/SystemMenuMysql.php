<?php
declare(strict_types=1);


namespace Models\Mysql;

use Models\Filed\FrameSystemMenu;
use Wlsh\AbstractPdo;

class SystemMenuMysql extends AbstractPdo
{
    public function getMenuList(array $data): array
    {
        return self::getDb()->from(FrameSystemMenu::Table)
            ->where($data['where'])
            ->orderBy(FrameSystemMenu::Id, 'DESC')
            ->offset($data['curr_data'])
            ->limit($data['page_size'])
            ->fetchAll();
    }

    public function getListCount(): int
    {
        return self::getDb()->from(FrameSystemMenu::Table)->select('count(*)')->fetchColumn();
    }

    public function getMenuInfo(): array
    {
        return self::getDb()->from(FrameSystemMenu::Table)
            ->select([
                FrameSystemMenu::Id,
                FrameSystemMenu::Name,
                FrameSystemMenu::Icon,
                FrameSystemMenu::UpId,
                FrameSystemMenu::Url,
                FrameSystemMenu::Level,
            ])
            ->fetchAll();
    }

}
