<?php

namespace WecarSwoole\Process;

use EasySwoole\Component\Process\AbstractProcess;
use EasySwoole\Component\Timer;
use Swoole\Event;
use Swoole\Process;

/**
 * easyswoole 的AbstractProcess存在bug：对SIGTERM捕获后没有终止当前进程，导致进程无法终止，从而导致整个服务无法被SIGTERM终止
 * 此处做修复
 */
abstract class WecarAbstractProcess extends AbstractProcess
{
    private $swProcess;

    public function __start(Process $process)
    {
        $this->swProcess = $process;
        parent::__start($process);
    }

    public function run($arg)
    {
        // 覆盖掉 AbstractProcess 中的事件注册
        Process::signal(SIGTERM, function () {
            Process::signal(SIGTERM, null);// 先取消掉该信号处理器
            swoole_event_del($this->swProcess->pipe);// 删除管道上的事件循环
            Timer::getInstance()->clearAll();// 清除定时器
            $this->onExit();
            Event::exit();// 退出事件循环
            Process::kill($this->getPid(), SIGTERM);// 再发一次SIGTERM终止当前进程
        });

        $this->exec($arg);
    }

    /**
     * 子类可重写此类增加进程退出前的逻辑
     */
    protected function onExit()
    {
        // nothing
    }

    abstract protected function exec($arg);
}
