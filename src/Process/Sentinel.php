<?php

namespace WecarSwoole\Process;

use Swoole\Timer;
use EasySwoole\EasySwoole\Config;

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
    private $canRun = true;

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

    public function beforeExec()
    {
        if ($this->onlyOnMaster) {
            // 优先判断环境变量
            if (self::hasMasterEnv()) {
                goto last;
            }

            $masterIp = Config::getInstance()->getConf('sentinel_server');
            if (!$masterIp || !in_array($masterIp, swoole_get_local_ip())) {
                $this->canRun = false;
                $this->willWriteFlag = false;
                echo "sentinel {$this->getProcessName()} not run:not master\n";
                return;
            }
        }

        last:
        parent::beforeExec();
    }

    /**
     * @param $arg
     * @throws \Throwable
     */
    protected function exec($arg)
    {
        if (!$this->canRun) {
            return;
        }

        foreach ($this->tasks as ['duration' => $duration, 'task' => $task]) {
            if (!is_callable($task)) {
                continue;
            }
            Timer::tick($duration * 1000, \Closure::fromCallable($task));
        }
    }

    /**
     * 是否设置了 master 环境变量
     * 在容器模式下，无法通过ip来判断是否需要执行，我们通过环境变量来判断
     * 此判断优先
     * @return bool
     */
    private static function hasMasterEnv(): bool
    {
        // 先看环境变量
        $isMaster = getenv('WECARSWOOLE_MASTER');
        if ($isMaster && trim($isMaster) == '1') {
            return true;
        }

        // 看常量
        if (defined('WECARSWOOLE_MASTER') && WECARSWOOLE_MASTER) {
            return true;
        }

        return false;
    }
}
