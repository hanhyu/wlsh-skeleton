<?php
declare(strict_types=1);

namespace Wlsh;

class DI
{
    protected array $container;

    public static function factory(): ?DI
    {
        //是否需要重新连接
        static $_obj = null;
        if (empty($_obj)) {
            $_obj = new self();
        }
        return $_obj;
    }

    /**
     * @param string $key
     * @param mixed $obj
     */
    public function set(string $key, mixed $obj): void
    {
        //todo 对象序列化存储
        $this->container[$key] = $obj;
    }

    /**
     *
     * @param string $key
     *
     * @return mixed
     */
    public function get(string $key): mixed
    {
        return $this->container[$key] ?? '';
    }

    public function getList(): array
    {
        return $this->container;
    }

    /**
     * @param string $key
     */
    public function del(string $key): void
    {
        $var = $this->container[$key] ?? '';
        unset($var);
    }

}
