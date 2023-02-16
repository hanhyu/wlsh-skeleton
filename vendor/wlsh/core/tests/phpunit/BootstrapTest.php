<?php
declare(strict_types=1);

use Wlsh\DI;

const APP_DEBUG = true;
//使用error_reporting来定义哪些级别错误可以触发 -1
error_reporting(E_ALL);

ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
ini_set('log_errors', '1');
define('SWOOLE_LOG_LEVEL', 2);
//每个PHP进程所吃掉的最大内存
ini_set('memory_limit', '2048M');

date_default_timezone_set('Asia/Shanghai');

const ROOT_PATH = '../../tests';
const DS = DIRECTORY_SEPARATOR;
const WLSH_CORE_PATH = __DIR__;
const CONF_PATH = ROOT_PATH . DS . 'config';

require __DIR__ . '/../../vendor/autoload.php';
require __DIR__ . '/../../src/Functions.php';

DI::factory()->set('config_arr', array_merge(
    require __DIR__ . '/../../tests/example/config/common.php',
    require __DIR__ . '/../../tests/example/config/local.php'
));
