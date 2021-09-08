<?php

namespace WecarSwoole\Util;

use Swoole\Coroutine\Channel;
use WecarSwoole\ErrCode;

/**
 * 并发执行多个业务逻辑，并等待所有业务逻辑执行完毕后一起返回所有的执行结果
 * 注意：必须在协程中使用
 * 返回：
 * 返回值数组里面依次存放有对应函数的返回结果：$rtns = [$r1, $r2]。
 * 如果任务抛出异常，则会将异常对象返回（\Throwable 实例），外面需要先判断返回值是否 \Throwable 类型
 * 可以通过调 throwError() 设置让任务直接对外抛异常--此时只要有一个执行抛异常则会向外面抛出异常
 */
class Concurrent
{
    private $params;
    private $tasks;
    private $throwError;

    public function __construct()
    {
        $this->reset();
    }

    /**
     * 便捷方法创建对象
     * 注意不是单例
     */
    public static function new(): Concurrent
    {
        return new self();
    }

    /**
     * 添加参数
     * 该方法要和 addTask 结合使用，$params 参数是对应的 task 的参数
     */
    public function addParams(...$params): Concurrent
    {
        $this->params[] = $params;
        return $this;
    }

    /**
     * 添加待执行任务
     */
    public function addTask($task): Concurrent
    {
        $this->tasks[] = $task;
        return $this;
    }

    /**
     * 是否直接向外面抛出异常
     */
    public function throwError(bool $throw = true): Concurrent
    {
        $this->throwError = $throw;
        return $this;
    }

    /**
     * 执行
     */
    public function exec(): array
    {
        return $this->execTasks($this->tasks, $this->params);
    }

    /**
     * 便捷调用方法（不支持传参），调用方一般使用 use 传参
     */
    public function simpleExec(...$tasks): array
    {
        return $this->execTasks($tasks);
    }

    private function execTasks(array $tasks, array $params = []): array
    {
        $tasks = array_filter(
            $tasks,
            function ($task) {
                return is_callable($task);
            }
        );

        if (!$tasks) {
            $this->reset();
            return [];
        }

        // 创建新协程执行单独的任务
        $channel = new Channel(count($tasks));
        $returns = [];
        foreach ($tasks as $index => $task) {
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

        // 等待
        for ($cnt = count($tasks); $cnt > 0; $cnt--) {
            $channel->pop();
        }

        // 如果要求直接抛出异常，则将所有的异常合并抛出
        if ($this->throwError) {
            $err = '';
            foreach ($returns as $rtn) {
                if (!$rtn instanceof \Throwable) {
                    continue;
                }

                $err .= $rtn . ';';
            }

            if ($err) {
                $this->reset();
                throw new \Exception($err, ErrCode::CONC_EXEC_FAIL);
            }
        }

        // 规整数组元素顺序
        ksort($returns);

        $this->reset();

        return $returns;
    }

    /**
     * 重置并发器
     */
    private function reset()
    {
        $this->params = [];
        $this->tasks = [];
        $this->throwError = false;
    }
}
