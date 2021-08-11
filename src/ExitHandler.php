<?php

namespace WecarSwoole;

use Closure;
use Swoole\Server;
use Swoole\Timer;

/**
 * worker/tasker 退出处理程序
 * 由于系统默认采用 reload_async 模式退出 worker 进程，此情况下会等待 worker 进程中所有协程退出后才退出进程，而如果
 * 有协程在跑无限循环、定时器，则无法退出，会导致进程一直等待（直到超时）
 * 在 onWorkerExit 中执行 exec 方法，并传入 $server 和 $workerId
 * 可以通过 addHandler 方法加入自己的退出处理程序，一般用来结束 while 无限循环、停止队列监听等
 * 系统默认会清理掉所有定时器
 */
class ExitHandler
{
    private static $handlers = [];
    private static $done = false;

    public static function addHandler(Closure $callable)
    {
        self::$handlers[] = $callable;
    }

    public static function exec(Server $server, int $workerId)
    {
        if (self::$done) {
            return;
        }

        self::$done = true;

        foreach (self::$handlers as $handler) {
            call_user_func($handler, $server, $workerId);
        }
    }
}

// 清理所有的定时器
ExitHandler::addHandler(
    function ($server, $workerId) {
        Timer::clearAll();
    }
);
