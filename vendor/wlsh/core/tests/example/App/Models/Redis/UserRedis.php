<?php
declare(strict_types=1);

namespace Models\Redis;

use RedisException;
use Wlsh\AbstractRedis;

class UserRedis extends AbstractRedis
{
    protected static int $db_index = 1;

    /**
     * @throws RedisException
     */
    public function getKey(string $key): bool|string
    {
        return self::getDb()->get($key);
    }

    /**
     * @throws RedisException
     */
    public function existToken(array $data): bool
    {
        return self::getDb()->sIsMember("user_id:{$data['uid']}", $data['token']);
    }
}
