<?php
/**
 * Created by PhpStorm.
 * UserDomain: yf
 * Date: 2019-01-06
 * Time: 21:49
 */

//php phpunit.php -c phpunit.xml domain/SystemTest.php
Swoole\Coroutine::set(['hook_flags' => SWOOLE_HOOK_ALL]);
Swoole\Coroutine\run(static function () {
    try {
        require_once __DIR__ . '/../../vendor/bin/phpunit';
    } catch (Throwable) {
    }
});
Swoole\Event::wait();

