<?php
declare(strict_types=1);

use Monolog\Logger;
use Swoole\Coroutine;
use Swoole\Http\Request;
use Wlsh\DI;
use Wlsh\FormsVali;
use Wlsh\ValidateException;

/**
 * 接口（表单）参数验证过滤器
 *
 * @param Request $request
 * @param array $validations
 *
 * @return array 返回验证过滤后的数据
 */
function validator(Request $request, array $validations): array
{
    $res_data = $request->getMethod() == 'GET' ? $request->get : post($request);

    $res_data = empty($res_data) ? [] : $res_data;
    try {
        $res_data = FormsVali::validate($res_data, $validations);
        $res_data = array_intersect_key($res_data, $validations);
    } catch (Exception $e) {
        throw new ValidateException($e->getMessage(), 400);
    }
    return $res_data;
}

/**
 * @throws ProgramException
 */
function post(Request $request): array
{
    $data = [];
    $content_type = $request->header['content-type'] ?? 'x-www-form-urlencoded';

    if (str_contains($content_type, 'json')) {
        if (!empty($request->getContent())) {
            try {
                $data = json_decode($request->getContent(), true, 512, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
            } catch (JsonException) {
                throw new ProgramException('无法处理请求内容', 422);
            }
        }
    } else if (!empty($request->post)) {
        $data = $request->post;
    }

    return $data;
}

/**
 * http协议以固定json格式返回信息
 *
 * @param int $code
 * @param string $msg
 * @param array $data
 * @param bool $vail
 *
 * @return string
 */
function httpJson(int $code = 200, string $msg = 'success', array $data = [], bool $vail = false): string
{
    $result = [];
    $result['code'] = $code;

    //由于只是获取header中的language值，为静态值，所以这里无需考虑协程数据混乱问题。
    //$cid = Coroutine::getCid();
    //$lang_code = DI::factory()->get('request_obj' . $cid)->header['language'] ?? '';
    $lang_code = Coroutine::getContext()->request->header['language'] ?? '';

    //屏蔽中文简体
    if ('zh-cn' === $lang_code) {
        $vail = true;
    }

    if ($msg and !$vail and $lang_code) {
        $result['msg'] = LANGUAGE[$lang_code][$msg] ?? '国际化：非法请求参数';
    } else {
        $result['msg'] = $msg;
    }

    $result['data'] = $data;
    try {
        $res = json_encode($result, 320 | JSON_THROW_ON_ERROR);
    } catch (Throwable $e) {
        $result['code'] = 400;
        $result['msg'] = $e->getMessage();
        $result['data'] = [];
        return json_encode($result, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
    }
    //debug_print_backtrace();
    return $res;
}

/**
 * ws协议以固定json格式返回信息
 *
 * @param int $code
 * @param string $msg
 * @param array $data
 * @param bool $vail
 *
 * @return string
 */
function wsJson(int $code = 200, string $msg = 'success', array $data = [], bool $vail = false): string
{
    $result = [];
    $result['code'] = $code;
    $result['uri'] = DI::factory()->get('ws_data_arr' . Coroutine::getCid())['uri'] ?? '';

    $lang_code = DI::factory()->get('ws_language_str');
    if ('zh-cn' === $lang_code) {
        $vail = true;
    }
    if ($msg and !$vail and $lang_code) {
        $result['msg'] = LANGUAGE[$lang_code][$msg] ?? '国际化：非法请求参数';
    } else {
        $result['msg'] = $msg;
    }

    $result['data'] = $data;
    try {
        $res = json_encode($result, 320 | JSON_THROW_ON_ERROR);
    } catch (Throwable $e) {
        $result['code'] = 400;
        $result['msg'] = $e->getMessage();
        $result['data'] = [];
        return json_encode($result, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
    }
    return $res;
}

/**
 * @param string $data
 * @return void
 */
function wsPush(string $data): void
{
    $server = DI::factory()->get('server_obj');
    $fd = Coroutine::getContext()->fd;

    if ($server->isEstablished($fd)) {
        $server->push($fd, $data);
    }
}

/**
 * swoole_http_sever获取用户IP地址
 *
 * @param array $server swoole_http_request->$server属性数组
 *
 * @return mixed|string
 */
function getIP(array $server): string
{
    if (!empty($server['http_client_ip'])) {
        $cip = $server['http_client_ip'];
    } elseif (!empty($server['http_x_forwarded_for'])) {
        $cip = $server['http_x_forwarded_for'];
    } elseif (!empty($server['remote_addr'])) {
        $cip = $server['remote_addr'];
    } else {
        $cip = '';
    }
    /*preg_match("/[\d\.]{7,15}/", $cip, $cips);
    $cip = $cips[0] ?? 'unknown';
    unset($cips);*/

    return $cip;
}

/**
 * @param $content
 * @param string $info
 * @return void
 */
function sendEmail($content, string $info): void
{
    $email = DI::factory()->get('email_config_arr');
    $transport = (new Swift_SmtpTransport($email['host'], $email['port']))
        ->setUsername($email['uname'])
        ->setPassword($email['pwd']);

    $mailer = new Swift_Mailer($transport);
    $body = $info . '<br />' . $content . '<br />记录时间：' . date('Y-m-d H:i:s');
    $message = (new Swift_Message($email['subject']))
        ->setFrom($email['from'])
        ->setTo($email['to'])
        ->setBody($body, 'text/html', 'utf-8');

    $mailer->send($message);
}

/**
 * monolog使用本地文件存储记录日志
 *
 * @param        $content
 * @param string $info
 * @param string $channel
 * @param string $level
 *
 * @throws Exception
 */
function monologByFile($content, string $info, string $channel, string $level): void
{
    $dir = date("Y-m-d");
    $log = new Logger($channel);
    $log->pushHandler(new StreamHandler(ROOT_PATH . "/log/monolog/{$dir}.log", Monolog\Logger::DEBUG));
    $log->pushProcessor(new ProcessIdProcessor());
    $log->pushProcessor(new UidProcessor());
    $log->pushProcessor(new MemoryUsageProcessor());
    $log->pushProcessor(new MemoryPeakUsageProcessor());
    if (is_array($content)) {
        $log->$level($info, $content);
    } else {
        $log->$level("$info {$content}");
    }
}


/**
 * 异步记录日志
 * 耗时的操作需使用此方法来处理，该方法是异步非阻塞模式。
 * 注意：如果是耗时的日志记录，必须使用taskLog方法异步处理，
 * 推荐在请求的路由生命周期内都使用taskLog记录日志，
 * critica、alert、emergency三种日志类型默认添加邮件通知。
 *
 * @param        $data
 * @param string $info
 * @param string $channel
 * @param string $level <p>debug (100): 详细的debug信息</p></P>
 *                                        <p>info (200): 有意义的事件，比如用户登录、SQL日志.</P>
 *                                        <p>notice (250): 普通但是重要的事件</P>
 *                                        <p>warning (300):
 *                                        异常事件，但是并不是错误。比如使用了废弃了的API，错误地使用了一个API，以及其他不希望发生但是并非必要的错误.</P>
 *                                        <p>error (400): 运行时的错误，不需要立即注意到，但是需要被专门记录并监控到.</P>
 *                                        <p>critica (500): 严重错误</P>
 *                                        <p>alert (550): 必须立即采取行动。比如整个网站都挂了，数据库不可用了等。这种情况应该发送短信警报，并把你叫醒.</P>
 *                                        <p>emergency (600): 紧急请求：系统不可用了</P>
 */
function taskLog($data, string $info, string $channel = 'system', string $level = 'info'): void
{
    DI::factory()->get('server_obj')->task(serialize([
        'uri' => '/task/log/index',
        'info' => $info,
        'channel' => $channel,
        'level' => $level,
        'content' => $data
    ]));
}

/**
 * 请求日志
 *
 * @param Request $request
 * @param string $level
 * @param string $resp_data
 */
function routerLog(Request $request, string $level = 'info', string $resp_data = ''): void
{
    $server = DI::factory()->get('server_obj');

    //todo 取请求对象指定参数值，如DEBUG=true
    //if ($request->header['DEBUG']) {
    $request_data['trace_id'] = Coroutine::getContext()->trace_id;
    $request_data['req_method'] = $request->server['request_method'];
    $request_data['req_uri'] = $request->server['request_uri'];
    $request_data['req_data'] = $request->getMethod() == 'GET' ? $request->get : post($request);
    $request_data['req_ip'] = getIP($request->server);
    $request_data['fd_time'] = $server->getClientInfo($request->fd)['last_time'] ?? time();
    $request_data['req_time'] = $request->server['request_time'];
    $request_data['resp_time'] = time();
    $request_data['resp_data'] = $resp_data;
    $request_data['level'] = $level;
    $request_data['uri'] = '/task/log/routerLog';
    $server->task(serialize($request_data));
    //}
}


/**
 * php时间戳带上微秒（13位）
 * @return float
 */
function msectime(): float
{
    [$msec, $sec] = explode(' ', microtime());
    return (float)sprintf('%.0f', ((float)$msec + (float)$sec) * 1000);
}

/**
 * 验证token的合法性、是否存在与过期
 *
 * @param string $token
 *
 * @return array
 * @throws JsonException
 */
function validateToken(string $token): array
{
    $res['code'] = 401;

    if (empty($token)) {
        $res['msg'] = '请先登录';
        return $res;
    }

    $data = tokenDecode($token);

    if (empty($data)) {
        taskLog($token, 'validateToken data fail:');
        $res['msg'] = '非法操作';
    } else {
        //设置登录时长过期时间
        $time_flag = (time() - (int)$data['time']) > (int)DI::factory()->get('config_arr')['token']['expTime'];
        if ($time_flag) {
            $res['msg'] = '登录超时';
        } else {
            $res = [];
        }
    }

    return $res;
}

/**
 * token加密参数
 *
 * @param array $params
 *
 * @return string
 * @throws JsonException
 */
function tokenEncode(array $params): string
{
    $encrypted = openssl_encrypt(
        json_encode($params, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
        'aes-256-cbc',
        base64_decode(DI::factory()->get('config_arr')['token']['encryptKey']),
        OPENSSL_RAW_DATA,
        base64_decode(DI::factory()->get('config_arr')['token']['encryptIv'])
    );
    return base64_encode($encrypted);
}

/**
 * token解密参数
 *
 * @param string $auth
 *
 * @return array
 * @throws JsonException
 */
function tokenDecode(string $auth): array
{
    $data = [];
    $token = base64_decode($auth);
    $decrypted = openssl_decrypt(
        $token,
        'aes-256-cbc',
        base64_decode(DI::factory()->get('config_arr')['token']['encryptKey']),
        OPENSSL_RAW_DATA,
        base64_decode(DI::factory()->get('config_arr')['token']['encryptIv'])
    );
    if ($decrypted) {
        $res = json_decode($decrypted, true, 512, JSON_THROW_ON_ERROR);
        if (json_last_error() === 0) {
            $data = $res;
        }
    }
    return $data;
}

/**
 * 数据签名规则
 * 如需要对返回的数据进行加密，请自行用https私钥加密，客户端可以用https公钥解密。
 *
 * @param int $cid
 * @param string $data
 */
function sign(int $cid, string $data): void
{
    $data = stripslashes($data);
    //简单的sign签名，如需用app_id、app_key颁发认证签名时请放进redis中和noncestr随机数
    if (DI::factory()->get('config_arr')['sign']['flag']) {
        $time = time();
        /*
        $sign = privateEncrypt(
                    md5($data . $time),
                    DI::factory()->get('config_arr')['sign']['prv_key']
                );
        */
        $sign = md5($data . $time);

        $resp = DI::factory()->get('response_obj' . $cid);
        $resp->header('timestamp', (string)$time);
        $resp->header('sign', $sign);
    }
}

/**
 * 私钥加密
 *
 * @param string $data
 * @param string $prv_key
 *
 * @return string
 */
function privateEncrypt(string $data, string $prv_key): string
{
    $encrypted = '';
    $key = openssl_pkey_get_private($prv_key);
    openssl_private_encrypt($data, $encrypted, $key);
    return base64_encode($encrypted);
}

/**
 * 私钥解密
 *
 * @param string $data
 * @param string $prv_key
 *
 * @return string
 */
function privateDecrypt(string $data, string $prv_key): string
{
    $decrypted = '';
    $key = openssl_pkey_get_private($prv_key);
    openssl_private_decrypt(base64_decode($data), $decrypted, $key);
    return $decrypted;
}

/**
 * 公钥加密
 *
 * @param string $data
 * @param string $pub_key
 *
 * @return string
 */
function publicEncrypt(string $data, string $pub_key): string
{
    $encrypted = '';
    $key = openssl_pkey_get_public($pub_key);
    openssl_public_encrypt($data, $encrypted, $key);
    return base64_encode($encrypted);
}

/**
 * 公钥解密
 *
 * @param string $data
 * @param string $pub_key
 *
 * @return string
 */
function publicDecrypt(string $data, string $pub_key): string
{
    $decrypted = '';
    $key = openssl_pkey_get_public($pub_key);
    openssl_public_decrypt(base64_decode($data), $decrypted, $key);
    return $decrypted;
}

/**
 * 验证手机号
 *
 * @param string $text
 *
 * @return bool
 */
function isMobile(string $text): bool
{
    $search = '/^0?1[3|4|5|6|7|8|9][0-9]\d{8}$/';
    if (preg_match($search, $text)) {
        return true;
    }

    return false;
}

/**
 * @param $password
 *
 * @return false|int
 */
function isMd5($password)
{
    return preg_match('/^[a-f0-9]{32}$/', $password);
}

/**
 * 数组分页
 * 一维数组直接返回分页键值数据；
 * 二维数组返回第二维数组键值数据
 *
 * @param array $array_data 数组
 * @param int $page 第几页
 * @param int $page_size 每页显示多少条
 *
 * @return array
 */
function arrayToPageData(array $array_data = [], int $page = 1, int $page_size = 10): array
{
    $array_data = array_values($array_data);
    $page_data['list'] = array_slice($array_data, ($page - 1) * $page_size, $page_size);
    $page_data['pagination']['total'] = count($array_data);
    $page_data['pagination']['current_page'] = count($array_data);
    $page_data['pagination']['pre_page_count'] = $page_size;
    return $page_data;
}
