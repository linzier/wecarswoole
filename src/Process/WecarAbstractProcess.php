<?php

namespace WecarSwoole\Process;

use EasySwoole\Component\Process\AbstractProcess;
use EasySwoole\Component\Timer;
use Swoole\Event;
use Swoole\Process;
use WecarSwoole\Util\File;

/**
 * easyswoole 的AbstractProcess存在bug：对SIGTERM捕获后没有终止当前进程，导致进程无法终止，从而导致整个服务无法被SIGTERM终止
 * 此处做修复
 */
abstract class WecarAbstractProcess extends AbstractProcess
{
    private $swProcess;
    protected $flagPrefix;
    protected $willWriteFlag = true;

    public function __start(Process $process)
    {
        $this->swProcess = $process;
        parent::__start($process);
    }

    public function setFlag(bool $willWriteFlag, string $flagPrefix = '')
    {
        $this->willWriteFlag = $willWriteFlag;
        $this->flagPrefix = $flagPrefix;
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

        $this->beforeExec();
        $this->exec($arg);
        $this->afterExec();
    }

    public function beforeExec()
    {
        $this->writeFlag();
    }

    abstract protected function exec($arg);

    public function afterExec()
    {
    }

    public function onExit()
    {
        $this->clearFlag();
    }

    private function writeFlag()
    {
        if (!$fname = $this->getFlagFileName()) {
            return;
        }

        file_put_contents($fname, date('Y-m-d H:i:s'));
    }

    private function clearFlag()
    {
        $fileName = $this->getFlagFileName();
        if ($fileName && file_exists($fileName)) {
            unlink($fileName);
        }
    }

    private function getFlagFileName(): string
    {
        $prefix = $this->flagPrefix ?: $this->getProcessName();
        if (!$this->willWriteFlag || !$prefix) {
            return '';
        }

        $pid = getmypid();
        $name = $prefix . '-' . $pid;

        return File::join(STORAGE_ROOT, "temp/{$name}.txt");
    }
}
