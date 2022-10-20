<?php

namespace WecarSwoole\Crontab;

use Cron\CronExpression;
use EasySwoole\Component\TableManager;
use EasySwoole\Component\Timer;
use EasySwoole\EasySwoole\Swoole\Task\TaskManager;
use WecarSwoole\CronTabUtil;
use WecarSwoole\Process\WecarAbstractProcess;

class WecarCronRunner extends WecarAbstractProcess
{
    protected $tasks;
    protected $willCheckServer = true;
    protected $willExec = true;

    public function setWillCheckServer(bool $willCheckServer)
    {
        $this->willCheckServer = $willCheckServer;
    }

    public function beforeExec()
    {
        if ($this->willCheckServer && !CronTabUtil::willRunCrontabNew()) {
            $this->willExec = false;
            $this->willWriteFlag = false;
            echo "crontab not run:not master server\n";
            return;
        }

        parent::beforeExec();
    }

    protected function exec($arg)
    {
        if (!$this->willExec) {
            echo date('Y-m-d H:i:s') . '.' . "swoole crontab:will not exec,return\n";
            return;
        }

        $this->tasks = $arg;
        $this->cronProcess();
        Timer::getInstance()->loop(29 * 1000, function () {
            echo date('Y-m-d H:i:s') . '.' . "swoole crontab:loop cronprocess\n";
            $this->cronProcess();
        });
    }

    private function cronProcess()
    {
        $table = TableManager::getInstance()->get(WecarCrontab::$__swooleTableName);
        foreach ($table as $taskName => $task) {
            $taskRule = $task['taskRule'];
            $nextRunTime = CronExpression::factory($task['taskRule'])->getNextRunDate();
            $distanceTime = $nextRunTime->getTimestamp() - time();
            if ($distanceTime < 30) {
                Timer::getInstance()->after($distanceTime * 1000, function () use ($taskName, $taskRule) {
                    echo date('Y-m-d H:i:s') . '.' . "swoole crontab:exec task:{$taskName}\n";
                    $nextRunTime = CronExpression::factory($taskRule)->getNextRunDate();
                    $table = TableManager::getInstance()->get(WecarCrontab::$__swooleTableName);
                    $table->incr($taskName, 'taskRunTimes', 1);
                    $table->set($taskName, ['taskNextRunTime' => $nextRunTime->getTimestamp()]);
                    TaskManager::processAsync($this->tasks[$taskName]);
                });
            }
        }
    }
}
