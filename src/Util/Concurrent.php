<?php

namespace WecarSwoole\Util;

use Swoole\Coroutine\Channel;
use WecarSwoole\ErrCode;

/**
 * 并发执行多个业务逻辑，并等待所有业务逻辑执行完毕后一起返回所有的执行结果
 * 返回：
 * 返回值数组里面依次存放有对应函数的返回结果：$rtns = [$r1, $r2]。
 * 如果任务抛出异常，则会将异常对象返回（\Throwable 实例），外面需要先判断返回值是否 \Throwable 类型
 */
class Concurrent
{
    private $params;
    private $tasks;
    private static $throwError = false;

    public static function instance(): Concurrent
    {
        return new self();
    }

    /**
     * 添加参数
     * 该方法要和 addTask 结合使用，$params 参数是对应的 task 的参数
     */
    public function addParams(...$params): Concurrent
    {
        if (!is_array($this->params)) {
            $this->params = [];
        }

        $this->params[] = $params;
        return $this;
    }

    /**
     * 添加待执行任务
     */
    public function addTask($task): Concurrent
    {
        if (!is_array($this->tasks)) {
            $this->tasks = [];
        }

        $this->tasks[] = $task;
        return $this;
    }

    public function throwError(bool $throw = true)
    {
        self::$throwError = $throw;
    }

    /**
     * 对外接口
     * $tasks 待执行函数
     */
    public function exec()
    {
        return self::execTasks($this->tasks, $this->params);
    }

    /**
     * 便捷调用方法（不支持传参），调用方一般使用 use 传参
     */
    public static function simpleExec(...$tasks): array
    {
        // 看最后一个是不是 bool，如果是，则该值表示是否对外抛出异常
        $last = $tasks[count($tasks) - 1];
        if (is_bool($last)) {
            self::$throwError = $last;
            array_pop($tasks);
        }
        return self::execTasks($tasks);
    }

    private static function execTasks(array $tasks, array $params = []): array
    {
        // 创建新协程执行单独的任务
        $channel = new Channel(count($tasks));
        $returns = [];
        $cnt = 0;
        foreach ($tasks as $index => $task) {
            if (!is_callable($task)) {
                continue;
            }

            $cnt++;
            go(function () use ($index, $task, $params, $channel, &$returns) {
                try {
                    $rtn = call_user_func($task, ...($params[$index] ?? []));
                } catch (\Throwable $e) {
                    $rtn = $e;
                }
                $returns[$index] = $rtn;
                $channel->push(1);
            });
        }

        for (; $cnt > 0; $cnt--) {
            $channel->pop();
        }

        // 如果要求直接抛出异常，则将所有的异常合并抛出
        if (self::$throwError) {
            $err = '';
            foreach ($returns as $rtn) {
                if (!$rtn instanceof \Throwable) {
                    continue;
                }

                $err .= $rtn . ';';
            }

            self::$throwError = false;
            throw new \Exception($err, ErrCode::CONC_EXEC_FAIL);
        }

        self::$throwError = false;

        return $returns;
    }
}
