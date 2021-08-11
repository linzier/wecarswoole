<?php

namespace WecarSwoole\Process;

use EasySwoole\Component\Process\AbstractProcess;
use Swoole\Coroutine;
use Swoole\Process;

/**
 * easyswoole 的AbstractProcess存在bug：对SIGTERM捕获后没有终止当前进程，导致进程无法终止，从而导致整个服务无法被SIGTERM终止
 * 此处做修复
 */
abstract class WecarAbstractProcess extends AbstractProcess
{
    function __start(Process $process)
    {
        Process::signal(SIGTERM, function () {
            go(function () {
                Process::signal(SIGTERM, null);// 先取消掉该信号处理器
                Coroutine::sleep(0.1);// 等待0.1秒，为的是让其他的处理程序先执行
                Process::kill($this->getPid(), SIGTERM);// 再发一次SIGTERM终止当前进程
            });
        });

        parent::__start($process);
    }
}
