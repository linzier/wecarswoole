<?php

namespace EasySwoole\EasySwoole;

use App\Bootstrap;
use WecarSwoole\CronTabUtil;
use EasySwoole\EasySwoole\Swoole\EventRegister;
use EasySwoole\EasySwoole\AbstractInterface\Event;
use EasySwoole\Component\Context\ContextManager;
use EasySwoole\Http\Request;
use EasySwoole\Http\Response;
use EasySwoole\Component\Di;
use WecarSwoole\ExitHandler;
use WecarSwoole\Process\ApolloWatcher;
use WecarSwoole\Process\HotReload;
use WecarSwoole\RequestId;

class EasySwooleEvent implements Event
{
    public static function initialize()
    {
        date_default_timezone_set('Asia/Shanghai');

        // HTTP 控制器命名空间
        Di::getInstance()->set(SysConst::HTTP_CONTROLLER_NAMESPACE, 'App\\Http\\Controllers\\');
    }

    /**
     * @param EventRegister $register
     * @throws \WecarSwoole\Exceptions\ConfigNotFoundException
     */
    public static function mainServerCreate(EventRegister $register)
    {
        // 热重启(仅用在非生产环境)
        if (Core::getInstance()->isDev() && HOT_RELOAD) {
            ServerManager::getInstance()->getSwooleServer()->addProcess(
                (new HotReload(
                    'HotReload',
                    [
                        'disableInotify' => true,
                        'monitorDirs' => [
                            EASYSWOOLE_ROOT . '/app',
                            EASYSWOOLE_ROOT . '/mock',
                            CONFIG_ROOT
                        ]
                    ]
                ))->getProcess()
            );
        }

        // worker 进程启动脚本
        $register->add(EventRegister::onWorkerStart, function () {
            Bootstrap::boot();
        });

        // worker 退出事件
        $register->add(EventRegister::onWorkerExit, function ($server, $workerId) {
            ExitHandler::exec($server, $workerId);
        });

        // 定时任务
        CronTabUtil::register();

        // Apollo 配置变更监听程序
        ServerManager::getInstance()->getSwooleServer()->addProcess((new ApolloWatcher())->getProcess());
    }

    /**
     * @param Request $request
     * @param Response $response
     * @return bool
     * @throws \EasySwoole\Component\Context\Exception\ModifyError
     */
    public static function onRequest(Request $request, Response $response): bool
    {
        // 设置 request id
        ContextManager::getInstance()->set('wcc-request-id', new RequestId($request));

        return true;
    }

    public static function afterRequest(Request $request, Response $response): void
    {
        // TODO: Implement afterRequest() method.
    }
}
