<?php
declare(strict_types=1);

use Swoole\Atomic;
use Swoole\Coroutine;
use Swoole\Event;
use Swoole\Http\Request;
use Swoole\Http\Response;
use Swoole\Server\Task;
use Swoole\Table;
use Swoole\Timer;
use Swoole\WebSocket\Server;
use Swoole\WebSocket\Frame;
use Wlsh\{Context, DI, FormsVali, ProgramException, Router, ValidateException};

/**
 * 注意此类中的每一行代码请勿随意上下移动
 *
 */
class Bootstrap
{
    /**
     * @var Server
     */
    private Server $server;
    private Table $table;
    private array $config;
    private Atomic $atomic;

    public function __construct($config)
    {
        $this->config = $config;
    }

    public function start(): void
    {
        $this->server = new Swoole\WebSocket\Server(
            '0.0.0.0',
            9770,
            SWOOLE_PROCESS,
            SWOOLE_SOCK_TCP
        //SWOOLE_SOCK_TCP | SWOOLE_SSL
        );

        $this->server->set($this->config);

        $this->table = new Table(1024);
        $this->table->column('rate_limit', Table::TYPE_INT, 128);
        $this->table->create();

        $this->atomic = new Atomic();

        /* $this->server->addListener('0.0.0.0', 9771, SWOOLE_SOCK_TCP)->set([
             'open_length_check'     => true,
             'package_length_type'   => 'N',
             'package_length_offset' => 0,
             'package_body_offset'   => 4,
         ]);*/

        $this->server->on('start', [$this, 'onStart']);
        $this->server->on('managerStart', [$this, 'onManagerStart']);
        $this->server->on('workerStart', [$this, 'onWorkerStart']);
        $this->server->on('workerStop', [$this, 'onWorkerStop']);
        $this->server->on('workerExit', [$this, 'onWorkerExit']);
        $this->server->on('handShake', [$this, 'onHandShake']);
        $this->server->on('open', [$this, 'onOpen']);
        $this->server->on('message', [$this, 'onMessage']);
        $this->server->on('request', [$this, 'onRequest']);
        $this->server->on('receive', [$this, 'onReceive']);
        $this->server->on('task', [$this, 'onTask']);
        $this->server->on('finish', [$this, 'onFinish']);
        $this->server->on('close', [$this, 'onClose']);
        $this->server->on('workerError', [$this, 'onWorkerError']);
        $this->server->start();
    }

    /**
     * @param Server $server
     */
    public function onStart(Server $server): void
    {
        //echo 'Swoole tcp server is started at tcp://127.0.0.1:9771' . PHP_EOL;
        echo 'Swoole http|ws server is started at http|s://127.0.0.1:9770' . PHP_EOL;
    }

    public function onManagerStart(Server $server): void
    {

    }

    /**
     * @param Server $server
     * @param int $worker_id
     *
     */
    public function onWorkerStart(Server $server, int $worker_id): void
    {
        //opcache_reset();
        /* array(3) {
                 [0]=>
           string(26) "/home/baseFrame/swoole.php"
                 [1]=>
           string(46) "/home/baseFrame/application/Bootstrap.php"
         }

         var_dump(get_included_files());*/

        require_once WLSH_CORE_PATH . '/vendor/autoload.php';
        require_once 'Functions.php';

        //重命名进程名字
        if ($server->taskworker) {
            swoole_set_process_name('swooleTaskProcess');
        } else {
            swoole_set_process_name('swooleWorkerProcess');
        }

        try {
            require_once CONF_PATH . DS . 'environ.php';
            require_once CONF_PATH . DS . 'language.php';

            DI::factory()->set('server_obj', $server);


            DI::factory()->set('table_obj', $this->table);
            DI::factory()->set('atomic_obj', $this->atomic);

            //把配置保存起来
            DI::factory()->set('config_arr', array_merge(
                require CONF_PATH . DS . 'common.php',
                require CONF_PATH . DS . CURRENT_ENV . '.php'
            ));

            //发送邮件配置
            DI::factory()->set('email_config_arr', require CONF_PATH . DS . 'sendEmail.php');
        } catch (Throwable $e) {
            print_r($e . PHP_EOL);
            $this->server->shutdown();
        }

        /*
         * 默认第一个工作进程发送websocket控制流0x9 ping帧，
         * js客户端websocket底层会自动回复pong包，这样就不用上游业务层做心跳包检测。
         *
         * 下面设置了每30秒向websocket客户端发送一个ping帧，
         * 配合heartbeat_idle_time=>600与heartbeat_check_interval=>60两个参数。
         * 说明：wlsh默认配置为每60秒检测一遍所有客户端fd（http、websocket等tcp连接标识符），
         * 如发现该fd在600秒之内没有发送一条消息，则关闭该连接; 此处设置表示http长连接最多保活10分钟。
         *
         */
        if (0 === $worker_id) {
            Timer::tick(30000, function () use ($server) {
                foreach ($server->connections as $fd) {
                    if ($this->server->isEstablished($fd)) {
                        $this->server->push($fd, 'true', 9);
                    }
                }
            });
        }
    }

    /**
     *
     * @param Request $request
     * @param Response $response
     *
     * @return bool
     */
    public function onHandShake(Request $request, Response $response): bool
    {
        //以get参数传递token，如： new WebSocket(`wss://127.0.0.1:9770?token=${token}`)
        /*$token = $request->get['token'] ?? '0';
        $res = validateToken($token);
        if (!empty($res)) {
            $response->status(401);
            $response->end($res['msg']);
            return false;
        }*/

        /*
         * 以子协议传递token，客户端初始化时需要传第二个参数，如： new WebSocket('wss://127.0.0.1:9770', token)
        $token_protocol = $request->header['sec-websocket-protocol'] ?? null;
        if (!is_null($token_protocol)) {
            $res = validateToken(urldecode($token_protocol));
            if (!empty($res)) {
                $response->status(400);
                $response->end();
                return false;
            }
        } else {
            $response->status(400);
            $response->end();
            return false;
        }
        */

        // websocket握手连接算法验证
        $secWebSocketKey = $request->header['sec-websocket-key'];
        $patten          = '#^[+/0-9A-Za-z]{21}[AQgw]==$#';
        if (0 === preg_match($patten, $secWebSocketKey) || 16 !== strlen(base64_decode($secWebSocketKey))) {
            $response->end();
            return false;
        }
        $key = base64_encode(sha1(
            $request->header['sec-websocket-key'] . '258EAFA5-E914-47DA-95CA-C5AB0DC85B11',
            true
        ));

        $headers = [
            'Upgrade'               => 'websocket',
            'Connection'            => 'Upgrade',
            'Sec-WebSocket-Accept'  => $key,
            'Sec-WebSocket-Version' => '13',
            //'Sec-WebSocket-Protocol' => $token_protocol,
        ];

        if (isset($request->header['sec-websocket-protocol'])) {
            $headers['Sec-WebSocket-Protocol'] = $request->header['sec-websocket-protocol'];
        }

        foreach ($headers as $key => $val) {
            $response->header($key, $val);
        }

        $response->status(101);
        $response->end();

        Event::defer(function () use ($request) {
            $this->onOpen($this->server, $request);
        });

        return true;
    }

    /**
     * 用户创建socket连接，记录fd值
     *
     * @param Server $server
     * @param Request $request
     */
    public function onOpen(Server $server, Request $request): void
    {
        if ($server->isEstablished($request->fd))
            $server->push($request->fd, wsJson(200, '连接成功'));
        //echo '===============' . date('Y-m-d H:i:s') . '欢迎' . $request->fd . '进入==============' . PHP_EOL;
    }

    /**
     * websocket协议路由转接
     *
     * @param Server $server
     * @param Frame $frame
     *
     * @throws JsonException
     */
    public function onMessage(Server $server, Frame $frame): void
    {
        if ($frame->opcode === 0x08) {
            //echo "Close frame received: Code {$frame->code} Reason {$frame->reason}\n";
        } else {
            try {
                $res = json_decode($frame->data, true, 512, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
            } catch (JsonException) {
                if ($server->isEstablished($frame->fd)) {
                    $server->push($frame->fd, wsJson(422, '无法处理请求内容'));
                }
            }

            if (!isset($res['uri']) and empty($res['uri'])) {
                if ($server->isEstablished($frame->fd)) {
                    $server->push($frame->fd, wsJson(400, '非法访问'));
                }
                $server->close($frame->fd, true);
                return;
            }

            Context::put('fd', $frame->fd);
            Context::put('ws_data_arr', $res);

            Coroutine::defer(function () {
                Context::delete();
            });

            //限流功能，该接口请求次数加1
            $this->table->incr($res['uri'], 'rate_limit', 1);

            try {
                $uri_arr = explode('/', $res['uri']);
                $this->routerStartup($uri_arr, 'WS');

                /*if ($server->isEstablished($frame->fd)) {
                    $server->push($frame->fd, $res);
                }*/
            } catch (ValidateException $e) { //参数验证手动触发的信息
                if ($server->isEstablished($frame->fd)) {
                    $server->push($frame->fd, wsJson($e->getCode(), $e->getMessage(), [], true));
                }
            } catch (ProgramException $e) { //程序手动抛出的异常
                if ($server->isEstablished($frame->fd)) {
                    $server->push($frame->fd, wsJson($e->getCode(), $e->getMessage()));
                }
            } catch (Throwable $e) {
                if ($server->isEstablished($frame->fd)) {
                    if (APP_DEBUG) {
                        $server->push($frame->fd, wsJson(500, $e->getMessage(), $e->getTrace()));
                    } else {
                        $server->push($frame->fd, wsJson(500, '服务异常'));
                    }
                }
            }

            //限流功能，该接口请求次数减1
            $this->table->decr($res['uri'], 'rate_limit', 1);
            error_clear_last();
            clearstatcache();
        }
    }

    /**
     * http协议路由转接
     *
     * @param Request $request
     * @param Response $response
     *
     * @throws JsonException
     */
    public function onRequest(Request $request, Response $response): void
    {
        $request_uri_str = $request->server['request_uri'] ?? '/';
        if (empty($request_uri_str)) {
            $response->status(404);
            $response->end();
            return;
        }
        //请求过滤,会请求2次
        if ('/favicon.ico' === $request->server['path_info'] or '/favicon.ico' === $request_uri_str) {
            $response->status(200);
            $response->end();
            return;
        }

        $response->header('Content-Type', 'application/json;charset=utf-8');

        $request_uri_arr = explode('/', $request_uri_str);
        if (isset($request_uri_arr[1]) and !empty($request_uri_arr[1])) {
            $response->header('Access-Control-Allow-Methods', 'POST,DELETE,PUT,GET,OPTIONS');
            $response->header('Access-Control-Allow-Headers', 'Content-Type,Authorization,Traceid');
            $response->header('Access-Control-Expose-Headers', 'Timestamp,Sign,Language,Traceid');
            $response->header('Access-Control-Allow-Credentials', 'true');
            $response->header('Access-Control-Max-Age', '8388608');
            $response->header('Access-Control-Allow-Origin', DI::factory()->get('config_arr')['origin']['domain']);

            //过滤掉固定的几个模块不能在外部http直接访问，ws、task、tcp、close、finish模块
            if (in_array($request_uri_arr[1], DI::factory()->get('config_arr')['deny_http_module'], true)) {
                $response->status(404);
                $response->end();
                return;
            }

            //预检
            if ($request->server['request_method'] === 'OPTIONS') {
                $response->status(200);
                $response->end();
                return;
            }
        }

        //注册全局信息
        /*多个协程是并发执行的，因此不能使用类静态变量/全局变量保存协程上下文内容。
        使用局部变量是安全的，因为局部变量的值会自动保存在协程栈中，
        其他协程访问不到协程的局部变量。*/

        Context::put('request', $request);
        Context::put('response', $response);
        Context::put('trace_id', $request->header['Traceid'] ?? $this->generalTraceId(getIP($request->server)));

        Coroutine::defer(function () {
            Context::delete();
        });

        //限流功能，该接口请求次数加1
        $this->table->incr($request_uri_str, 'rate_limit', 1);

        $response->status(200);
        $response->header('Traceid', Context::get('trace_id'));

        try {
            $this->routerStartup($request_uri_arr, $request->getMethod());
            //$response->end($res);
        } catch (ValidateException $e) { //参数验证手动触发的信息
            $res = httpJson($e->getCode(), $e->getMessage(), vail: true);
            $response->end($res);
            routerLog($request, 'notice', $res);
        } catch (ProgramException $e) { //程序手动抛出的异常
            $res = httpJson($e->getCode(), $e->getMessage());
            $response->end($res);
            routerLog($request, 'warning', $res);
        } catch (Throwable $e) {
            if (APP_DEBUG) {
                $response->end(httpJson(500, $e->getMessage(), $e->getTrace()));
            } else {
                $response->end(httpJson(500, '服务异常'));
            }

            routerLog(
                $request,
                'error',
                json_encode(['message' => $e->getMessage(), 'trace' => $e->getTrace()], JSON_THROW_ON_ERROR | 320)
            );
        }

        //限流功能，该接口请求次数减1
        $this->table->decr($request_uri_str, 'rate_limit', 1);
        error_clear_last();
        clearstatcache();
    }

    /**
     * tcp协议路由转接
     *
     * @param Server $server
     * @param int $fd
     * @param int $reactor_id
     * @param string $data
     *
     * @throws JsonException
     */
    //todo 暂未实现路由Tcp模块
    public function onReceive(Server $server, int $fd, int $reactor_id, string $data): void
    {
        $data = substr($data, 4);
        $res  = json_decode($data, true, 512, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);

        Context::put('fd', $fd);
        Context::put('receive_data_arr', $res);

        Coroutine::defer(function () {
            Context::delete();
        });

        try {
            $uri_arr = explode('/', $res['uri']);
            $this->routerStartup($uri_arr, 'Cli');
        } catch (Throwable $e) {
            taskLog(
                ['message' => $e->getMessage(), 'trace' => $e->getTrace()],
                'onReceive Throwable message:',
                'receive',
                'error'
            );
        }
    }

    /**
     * http协议中使用task方法,只限用于在worker操作方法中调用task时不依赖task方法返回的结果,如:redis,mysql等插入操作且不需返回插入后的状态.
     * websocket协议中用task方法,可直接在task方法中调用push方法返回数据给客户端,这样swoole服务模式就变为worker中方法是异步
     * 到task方法中同步+协程执行模式,worker中可更多地处理请求以提高websocket服务器性能.
     * task路由转接
     *
     * @param Server $server
     * @param Task $task
     *
     * @throws Exception
     */
    public function onTask(Server $server, Task $task): void
    {
        $res = unserialize((string)$task->data);

        Context::put('task_data_arr', $res);

        Coroutine::defer(function () {
            Context::delete();
        });

        try {
            $uri_arr = explode('/', $res['uri']);
            $this->routerStartup($uri_arr, 'TASK');
        } catch (Throwable $e) {
            file_put_contents(
                LOG_PATH . '/task.log',
                PHP_EOL . microtime() . ':' . json_encode(['message' => $e->getMessage(), 'trace' => $e->getTrace()], JSON_THROW_ON_ERROR | 320),
                FILE_APPEND
            );
        }

        //$server->finish();
    }

    /**
     * task任务完成返回数据到worker时路由转接
     *
     * @param Server $server
     * @param int $task_id
     * @param string $data
     */
    public function onFinish(Server $server, int $task_id, string $data): void
    {
    }

    /**
     * 连接关闭路由转接
     *
     * @param Server $server
     * @param int $fd
     * @param int $reactorId
     */
    public function onClose(Server $server, int $fd, int $reactorId): void
    {
        // $status = \Wlsh\PdoPool::getInstance('mysql')->getStatus();
        //var_dump($status);
    }

    /**
     * 此事件在worker进程终止时发生。在此函数中可以回收worker进程申请的各类资源
     *
     * @param Server $server
     * @param int $worker_id
     */
    public function onWorkerStop(Server $server, int $worker_id): void
    {
        //请勿开启opcache，如开启了需要在这里使用opcache_reset();
    }

    /**
     * 在onWorkerExit中尽可能地移除/关闭异步的Socket连接，最终底层检测到Reactor中事件监听的句柄数量为0时退出进程。
     *
     * @param Server $server
     * @param int $worker_id
     */
    public function onWorkerExit(Server $server, int $worker_id): void
    {

    }

    /**
     * 此函数主要用于报警和监控，一旦发现Worker进程异常退出，那么很有可能是遇到了致命错误或者进程CoreDump。
     * 通过记录日志或者发送报警的信息来提示开发者进行相应的处理
     *
     * @param Server $server
     * @param int $worker_id
     * @param int $worker_pid
     * @param int $exit_code
     * @param int $signal
     */
    public function onWorkerError(Server $server, int $worker_id, int $worker_pid, int $exit_code, int $signal): void
    {
        file_put_contents(
            LOG_PATH . '/workerError.log',
            microtime() . ':' . "onWorkerError: pid:{$worker_pid},code:{$exit_code},signal:{$signal}",
            FILE_APPEND
        );
    }

    /**
     * User: hanhyu
     * Date: 2019/12/4
     * Time: 下午10:05
     *
     * @param array $uri_arr 请求的链接地址
     * @param string $method 请求的方法
     *
     * @throws ProgramException
     */
    public function routerStartup(array $uri_arr, string $method): void
    {
        /*可以在这个钩子函数routerShutdown中做拦截处理，获取当前URI，以当前URI做KEY，判断是否存在该KEY的缓存，
         若存在则停止解析，直接输出页面，缓存数据页。
         或做防重复操作提交*/
        /**
         * $arr[1] module
         * $arr[2] controller
         * $arr[3] action
         */
        switch (count($uri_arr)) {
            case 5:
                $ctrl   = 'Modules\\' . ucfirst($uri_arr[1]) . '\Controllers\\' . ucfirst($uri_arr[2]) . '\\' . ucfirst($uri_arr[3]) . 'Controller';
                $action = $uri_arr[4] . 'Action';
                break;
            case 4:
                $ctrl   = 'Modules\\' . ucfirst($uri_arr[1]) . '\Controllers\\' . ucfirst($uri_arr[2]) . 'Controller';
                $action = $uri_arr[3] . 'Action';
                break;
            default:
                $ctrl   = 'Controllers\\' . ucfirst($uri_arr[1]) . 'Controller';
                $action = ($uri_arr[2] ?? '') . 'Action';
        }

        try {
            $ref = new \ReflectionMethod($ctrl, $action);
            /**
             * @var $ref_router Router
             */
            $ref_router = $ref->getAttributes(Router::class)[0]?->newInstance();

            if (empty($ref_router)) {
                throw new ProgramException('请求的接口不存在', 500);
            }

            if ($method == 'CLI') {
                $ref_router->method = 'Cli';
            }

            if ($method !== $ref_router->method) {
                throw new ProgramException('请求方法不正确', 405);
            }

            if (true === $ref_router->auth) {
                $this->authToken();
            }

            //接口限流
            $this->rateLimit(implode('/', $uri_arr), $ref_router->rate_limit);

            if (class_exists($ctrl)) {
                $class = new $ctrl();
                if (method_exists($class, $action)) {
                    DI::factory()->get('atomic_obj')->add(1);

                    switch ($ref_router->method) {
                        case 'GET':
                        case 'POST':
                            $request  = Context::get('request');
                            $response = Context::get('response');
                            break;
                        case 'WS':
                            $request  = Context::get('ws_data_arr');
                            $response = new stdClass();
                            break;
                        case 'TASK':
                            $request  = Context::get('task_data_arr');
                            $response = new stdClass();
                            break;
                        default:
                            $request  = new stdClass();
                            $response = new stdClass();
                    }

                    $lang_code = $request->header['language'] ?? 'zh-cn';
                    if (empty($lang_code)) {
                        FormsVali::setLangCode('zh-cn');
                    } else {
                        FormsVali::setLangCode($lang_code);
                    }

                    if (!empty($ref_router->before)) {
                        $before_action = $ref_router->before;
                        $class->$before_action();
                    }

                    $class->$action($request, $response);

                    if (!empty($ref_router->after)) {
                        $after_action = $ref_router->after;
                        $class->$after_action();
                    }
                }
            } else {
                throw new ProgramException('非法请求', 400);
            }
        } catch (\ReflectionException) {
            throw new ProgramException('请求的接口不存在', 400);
        }
    }

    /**
     * 请求该路由是否需要授权的token
     *
     * @throws Exception
     * @todo 还需要进一步验证token是否属于用户
     */
    private function authToken(): void
    {
        //验证授权token的合法性与过期时间
        $token = Context::get('request')->header['authorization'] ?? '0';

        $res = validateToken($token);
        if (!empty($res)) {
            throw new ProgramException($res['msg'], $res['code']);
        }
    }

    /**
     * 接口限流
     *
     * User: hanhyu
     * Date: 2021/2/24
     * Time: 下午9:47
     *
     * @param string $uri_str 请求的接口uri值
     * @param int $rate_limit 该接口最大Qps|Tps
     *
     * @throws ProgramException
     */
    private function rateLimit(string $uri_str, int $rate_limit): void
    {
        if (
            ('prod' === CURRENT_ENV)
            and $rate_limit > 0
            and (DI::factory()->get('table_obj')->get($uri_str, 'rate_limit') > $rate_limit)
        ) {
            throw new ProgramException('服务繁忙中，请稍候再试！', 500);
        }
    }

    private function generalTraceId($ip_address): string
    {
        return $ip_address . "_" . getmypid() . "_" . (microtime(true) - 1609430400) . "_" . mt_rand(0, 255);
    }

}
