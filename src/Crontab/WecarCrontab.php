<?php

namespace WecarSwoole\Crontab;

use Swoole\Table;
use Cron\CronExpression;
use EasySwoole\Component\Singleton;
use EasySwoole\Component\TableManager;
use EasySwoole\EasySwoole\Crontab\AbstractCronTask;
use EasySwoole\EasySwoole\Crontab\Exception\CronTaskNotExist;
use EasySwoole\EasySwoole\Crontab\Exception\CronTaskRuleInvalid;
use EasySwoole\EasySwoole\ServerManager;

/**
 * 由于easyswoole的Crontab里面用的AbstractProcess有问题，此处重写 Crontab
 * Class WecarCrontab
 * @package WecarSwoole\Crontab
 */
class WecarCrontab
{
    use Singleton;

    private $tasks = [];
    private $willCheckServer;
    /*
     * 下划线开头表示不希望用户使用
     */
    public static $__swooleTableName = '__CrontabRuleTable';

    public function __construct(bool $willCheckServer = true)
    {
        $this->willCheckServer = $willCheckServer;
    }

    /*
     * 同名任务会被覆盖
     */
    function addTask(string $cronTaskClass): WecarCrontab
    {
        try {
            $ref = new \ReflectionClass($cronTaskClass);
            if ($ref->isSubclassOf(AbstractCronTask::class)) {
                $taskName = $ref->getMethod('getTaskName')->invoke(null);
                $taskRule = $ref->getMethod('getRule')->invoke(null);
                if (CronExpression::isValidExpression($taskRule)) {
                    $this->tasks[$taskName] = $cronTaskClass;
                } else {
                    throw new CronTaskRuleInvalid($taskName, $taskRule);
                }
                return $this;
            } else {
                throw new \InvalidArgumentException("the cron task class {$cronTaskClass} is invalid");
            }
        } catch (\Throwable $throwable) {
            throw new \InvalidArgumentException("the cron task class {$cronTaskClass} is invalid");
        }
    }

    /**
     * 重新设置某个任务的规则
     * @param string $taskName
     * @param string $taskRule
     * @throws CronTaskNotExist
     * @throws CronTaskRuleInvalid
     */
    function resetTaskRule(string $taskName, string $taskRule)
    {
        $table = TableManager::getInstance()->get(self::$__swooleTableName);
        if ($table->exist($taskName)) {
            if (CronExpression::isValidExpression($taskRule)) {
                $table->set($taskName, ['taskRule' => $taskRule]);
            } else {
                throw new CronTaskRuleInvalid($taskName, $taskRule);
            }
        } else {
            throw new CronTaskNotExist($taskName);
        }
    }

    /**
     * 获取某任务当前的规则
     * 在服务启动完成之前请勿调用！
     * @param string $taskName 任务名称
     * @return string 任务当前规则
     * @throws CronTaskNotExist|\Exception
     */
    function getTaskCurrentRule($taskName)
    {
        $taskInfo = $this->getTableTaskInfo($taskName);
        return $taskInfo['taskRule'];
    }

    /**
     * 获取某任务下次运行的时间
     * 在服务启动完成之前请勿调用！
     * @param string $taskName 任务名称
     * @return integer 任务下次执行的时间戳
     * @throws \Exception
     */
    function getTaskNextRunTime($taskName)
    {
        $taskInfo = $this->getTableTaskInfo($taskName);
        return $taskInfo['taskNextRunTime'];
    }

    /**
     * 获取某任务自启动以来已运行的次数
     * 在服务启动完成之前请勿调用！
     * @param string $taskName 任务名称
     * @return integer 已执行的次数
     * @throws \Exception
     */
    function getTaskRunNumberOfTimes($taskName)
    {
        $taskInfo = $this->getTableTaskInfo($taskName);
        return $taskInfo['taskRunTimes'];
    }

    /**
     * 获取表中存放的Task信息
     * @param string $taskName 任务名称
     * @return array 任务信息
     * @throws CronTaskNotExist|\Exception
     */
    private function getTableTaskInfo($taskName)
    {
        $table = TableManager::getInstance()->get(self::$__swooleTableName);
        if ($table) {
            if ($table->exist($taskName)) {
                return $table->get($taskName);
            } else {
                throw new CronTaskNotExist($taskName);
            }
        } else {
            throw new \Exception('Crontab tasks have not yet started!');
        }
    }

    function run()
    {
        if (!$this->tasks) {
            return;
        }

        echo date('Y-m-d H:i:s') . '.' . "swoole crontab:start to run crontab\n";

        $server = ServerManager::getInstance()->getSwooleServer();
        $runner = new WecarCronRunner("crontab", $this->tasks);
        $runner->setWillCheckServer($this->willCheckServer);

        // 将当前任务的初始规则全部添加到swTable管理
        TableManager::getInstance()->add(self::$__swooleTableName, [
            'taskRule' => ['type' => Table::TYPE_STRING, 'size' => 35],
            'taskRunTimes' => ['type' => Table::TYPE_INT, 'size' => 4],
            'taskNextRunTime' => ['type' => Table::TYPE_INT, 'size' => 4]
        ], 1024);

        $table = TableManager::getInstance()->get(self::$__swooleTableName);

        // 由于添加时已经确认过任务均是AbstractCronTask的子类 这里不再去确认
        foreach ($this->tasks as $cronTaskName => $cronTaskClass) {
            $taskRule = $cronTaskClass::getRule();
            $nextTime = CronExpression::factory($taskRule)->getNextRunDate()->getTimestamp();
            $table->set($cronTaskName, ['taskRule' => $taskRule, 'taskRunTimes' => 0, 'taskNextRunTime' => $nextTime]);
        }

        $server->addProcess($runner->getProcess());
    }
}
