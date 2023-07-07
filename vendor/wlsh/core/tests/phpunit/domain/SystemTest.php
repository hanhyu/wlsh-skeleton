<?php
declare(strict_types=1);


use Domain\System\MenuDomain;
use Domain\System\UserDomain;
use Models\Redis\UserRedis;
use PHPUnit\Framework\TestCase;

class SystemTest extends TestCase
{
    public function testGetInfoList(): void
    {
        $data = [
            'curr_page' => 1,
            'page_size' => 1,
        ];

        for ($i = 0; $i < 10; $i++) {
            $menu = new MenuDomain();
            $res = $menu->getList($data);
            self::assertEquals(12, $res['count']);

            $user = new UserDomain();
            $res_user = $user->getInfoList($data);
            self::assertEquals(17, $res_user['count']);

            $token = UserRedis::getInstance()->getKey('token');
            self::assertEquals('123', $token);
        }
    }

}