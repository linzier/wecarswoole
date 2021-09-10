<?php

namespace WecarSwoole\Process;

use App\Bootstrap;
use Swoole\Timer;
use EasySwoole\EasySwoole\Config;
use WecarSwoole\Util\File;

/**
 * 哨兵，主要做各种监控
 * 一般只在主服务器（通过配置指定）上执行
 * 注意：如果服务需要启动多个Sentinel（如其中一个仅在主服执行，另一个需要在所有服务器执行），建议为每个Sentinel设置名称（构造函数第一个参数）
 * Class Sentinel
 * @package WecarSwoole\Process
 */
class Sentinel extends WecarAbstractProcess
{
    private $tasks = [];
    private $onlyOnMaster = true;

    public function onlyOnMaster(bool $onoff = true): Sentinel
    {
        $this->onlyOnMaster = $onoff;
        return $this;
    }

    /**
     * 添加执行的任务
     * @param int $duration 多长时间执行一次
     * @param \Closure $closure 执行内容，必须是闭包，如果不是（但属于callable）需要用Closure::fromCallable转换成Closure
     * @return Sentinel
     */
    public function addTask(int $duration, \Closure $closure): Sentinel
    {
        $this->tasks[] = ['duration' => $duration, 'task' => $closure];
        return $this;
    }

    /**
     * @param $arg
     * @throws \Throwable
     */
    protected function exec($arg)
    {
        Bootstrap::boot();

        if ($this->onlyOnMaster) {
            $masterIp = Config::getInstance()->getConf('sentinel_server');
            if (!$masterIp || !in_array($masterIp, swoole_get_local_ip())) {
                echo "sentinel {$this->getProcessName()} not run:not master\n";
                return;
            }
        }

        $this->writeFlag();

        foreach ($this->tasks as ['duration' => $duration, 'task' => $task]) {
            if (!is_callable($task)) {
                continue;
            }
            Timer::tick($duration * 1000, \Closure::fromCallable($task));
        }
    }

    public function onExit()
    {
        $this->clearFlag();
    }

    private function writeFlag()
    {
        file_put_contents($this->getFlagFileName(), date('Y-m-d H:i:s'));
    }

    private function clearFlag()
    {
        $fileName = $this->getFlagFileName();
        if (file_exists($fileName)) {
            unlink($fileName);
        }
    }

    private function getFlagFileName(): string
    {
        $pid = getmypid();
        $name = $this->getProcessName() ? $this->getProcessName() . '-' . $pid : $pid;
        return File::join(STORAGE_ROOT, "temp/sentinel-{$name}.txt");
    }
}
