<?php
declare(strict_types=1);

namespace Wlsh;

use PDO;
use PDOException;
use Swoole\Coroutine\Channel;

/**
 * Created by PhpStorm.
 * UserDomain: hanhui
 * Date: 18-2-13
 * Time: 下午9:11
 */
final class PdoPool
{
    /**
     * @var Channel
     */
    protected Channel $ch;
    private array $config;
    private static PdoPool $instance;

    /**
     * 每个进程默认生成2个长连接对象,运行中不够则自动扩容
     * PdoPool constructor.
     *
     * @param string $db_type 数据库类型：mysql、pgsql等
     * @param int $pool_min 启动的单进程中初始化默认最小连接池为2
     * @param int $pool_max 启动的单进程中初始化默认最大连接池为10
     *
     * @throws PDOException
     */
    private function __construct(string $db_type, int $pool_min = 2, int $pool_max = 10)
    {
        $this->ch     = new Channel($pool_max);
        $this->config = DI::factory()->get('config_arr')[$db_type];
        try {
            for ($i = 0; $i < $pool_min; $i++) {
                $this->ch->push($this->connect());
            }
        } catch (PDOException $e) {
            throw new PDOException($e->getMessage());
        }
    }

    public static function getInstance(string $db_type = 'mysql'): PdoPool
    {
//todo 这种写法需要测试同一次请求流程中调用不同的数据库，是否有冲突问题
        if (empty(self::$instance)) {
            self::$instance = new self($db_type);
        }

        return self::$instance;
    }

    /**
     * 获取mysql连接，如池子内有连接就取一个，连接不够则新建一个。
     * @return PDO
     */
    public function get(): PDO
    {
        $db = $this->ch->pop(3);
        /**
         * 判断此空闲连接是否已被断开，已断开就重新请求连接，
         * 这里使用channel的pop功能就实现了一个判断池子中的连接是否超过空闲时间，如超时mysql则会自动断开此连接，
         * 当ping检查连接不可用时，就丢弃此连接（pop消息时连接池就没了此连接对象）并重新建立一个新的连接对象，
         * 此功能依赖于mysql的wait_timeout与interactive_timeout两个参数值。
         */
        if ($db === false) {
            $db = $this->connect();
        }

        //连接失败，进行三次重连机制
        for ($i = 0; $i < 3; $i++) {
            //这种合并写法，池子性能降低10%
            if ($db != false and $this->ping($db)) {
                usleep(100);
                $db = $this->connect();
            } else {
                break;
            }
        }

        //延迟向连接池中存入连接对象，让后面的客户端可以复用此连接。
        //此方法不能放在finally中。
        defer(function () use ($db) {
            if ($db instanceof PDO) {
                $this->ch->push($db);
            }
        });

        return $db;
    }

    public function put(PDO $db): void
    {
        $this->ch->push($db);
    }

    /**
     * UserDomain: hanhyu
     * Date: 19-7-5
     * Time: 上午10:52
     * @return PDO
     */
    public function connect(): PDO
    {
        $retry = 3;
        while ($retry) {
            try {
                $connectionString = sprintf(
                    "%s:dbname=%s;host=%s;port=%s;charset=%s",
                    $this->config['driver'],
                    $this->config['database'],
                    $this->config['host'],
                    $this->config['port'],
                    $this->config['charset']
                );
                return new PDO(
                    $connectionString,
                    //"{$this->config['driver']}:dbname={$this->config['database']};host={$this->config['host']};port={$this->config['port']};charset={$this->config['charset']}",
                    $this->config['username'] ?? null,
                    $this->config['password'] ?? null,
                    [
                        PDO::ATTR_CASE                     => PDO::CASE_NATURAL,
                        PDO::ATTR_ERRMODE                  => PDO::ERRMODE_EXCEPTION,
                        PDO::ATTR_ORACLE_NULLS             => PDO::NULL_NATURAL,
                        PDO::ATTR_TIMEOUT                  => 3,
                        PDO::ATTR_DEFAULT_FETCH_MODE       => PDO::FETCH_ASSOC,
                        PDO::ATTR_STRINGIFY_FETCHES        => false,
                        //持久连接禁用以避免可能的资源泄露和死锁问题。
                        //PDO::ATTR_PERSISTENT               => true,
                        PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => true,
                        PDO::MYSQL_ATTR_INIT_COMMAND       => "SET NAMES 'utf8';",
                    ]);
            } catch (PDOException $e) {
                $retry--;
                if (!$retry) {
                    throw new PDOException($e->getMessage());
                }
                sleep(1);
            }
        }
        throw new \RuntimeException("Failed to connect to the database after multiple attempts.");
    }

    /**
     * 获取连接池使用状态
     * UserDomain: hanhyu
     * Date: 19-7-17
     * Time: 上午11:09
     * @return array
     */
    public function getStatus(): array
    {
        return $this->ch->stats();
    }

    /**
     * 检查连接是否可用
     *
     * @param PDO $dbconn 数据库连接
     *
     * @return bool ping通了返回false,ping不通返回true
     */
    public function ping(PDO $dbconn): bool
    {
        try {
            $dbconn->getAttribute(PDO::ATTR_SERVER_INFO);
        } catch (PDOException $e) {
            //if (strpos($e->getMessage(), 'MySQL server has gone away') !== false) {
            if (!empty($e->errorInfo) and ($e->errorInfo[1] === 2006 or $e->errorInfo[1] === 2013)) {
                return true;
            }
        }
        return false;
    }

}
