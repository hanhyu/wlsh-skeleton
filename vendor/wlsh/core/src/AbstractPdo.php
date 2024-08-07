<?php declare(strict_types=1);

namespace Wlsh;

use Swoole\Coroutine;

/**
 * Created by PhpStorm.
 * UserDomain: hanhyu
 * Date: 18-10-28
 * Time: 下午3:34
 */
abstract class AbstractPdo implements ModelInterface
{
    private static array $instance = [];
    /**
     * 此处使用静态延迟绑定，实现选择不同的数据库
     * @var int
     */
    protected static string $db = 'mysql';

    public static function getInstance(): static
    {
        $_class_name = static::class;
        $_cid = Coroutine::getCid();
        if (!isset(static::$instance[$_class_name][$_cid])) {
            //new static()与new static::class一样，但为了IDE友好提示类中的方法，需要用new static()
            static::$instance[$_class_name][$_cid] = new static();
        }

        defer(static function () use ($_class_name, $_cid) {
            unset(static::$instance[$_class_name][$_cid]);
        });

        //为了IDE代码提示功能
        return static::$instance[$_class_name][$_cid];
    }

    private function __construct()
    {
    }

    /**
     * 使用单例可以最大化在协程内利用pdo连接池对象
     *
     * User: hanhyu
     * Date: 2021/1/30
     * Time: 上午10:19
     *
     * @return Db
     */
    public static function getDb(): Db
    {
        $_class_name = static::class;
        $_cid = Coroutine::getCid();
        $query = static::$instance[$_class_name]['query'][$_cid] ?? '';
        if (empty($query)) {
            $query = static::$instance[$_class_name]['query'][$_cid] = new Db(PdoPool::getInstance(static::$db)->get());
        }

        defer(static function () use ($_class_name, $_cid) {
            unset(static::$instance[$_class_name]['query'][$_cid]);
        });

        return $query;
    }

}
