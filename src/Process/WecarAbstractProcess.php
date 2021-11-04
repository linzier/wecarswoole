<?php

namespace WecarSwoole\Process;

use WecarSwoole\Bootstrap;
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

    public function __construct($processName = '', $arg = null, $redirectStdinStdout = false, $pipeType = 2, $enableCoroutine = true)
    {
        parent::__construct($processName, $arg, $redirectStdinStdout, $pipeType, $enableCoroutine);
    }

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

    /**
     * @param $arg
     * @throws \Throwable
     */
    public function run($arg)
    {
        Process::signal(SIGTERM, \Closure::fromCallable([$this, 'signalHandle']));
        Process::signal(SIGINT, \Closure::fromCallable([$this, 'signalHandle']));

        Bootstrap::boot();
        $this->beforeExec();
        $this->exec($arg);
        $this->afterExec();
    }

    protected function beforeExec()
    {
        $this->writeFlag();
    }

    abstract protected function exec($arg);

    protected function afterExec()
    {
    }

    /**
     * 处理终止信号
     * @param $signo
     */
    protected function signalHandle($signo) {
        if ($signo != SIGTERM && $signo != SIGINT) {
            return;
        }

        Process::signal($signo, null);// 先取消掉该信号处理器
        swoole_event_del($this->swProcess->pipe);// 删除管道上的事件循环
        Timer::getInstance()->clearAll();// 清除定时器
        Event::exit();// 退出事件循环
        $this->onExit();
        Process::kill(getmypid(), $signo);// 再发一次，退出进程（不能用exit，否则swoole报错）
    }

    protected function onExit()
    {
        $this->clearFlag();
    }

    private function writeFlag()
    {
        if (!$fname = $this->getFlagFileName()) {
            return;
        }

        file_put_contents($fname, "pid:" . getmypid() . ",time:" . date('Y-m-d H:i:s'));
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
        $name = $this->flagPrefix ?: $this->getProcessName();
        if (!$this->willWriteFlag || !$name) {
            return '';
        }

        return File::join(STORAGE_ROOT, "temp/{$name}.txt");
    }
}
