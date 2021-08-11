<?php

namespace WecarSwoole\Queue;

use EasySwoole\Queue\Job;

/**
 * 队列监听
 */
class QueueListener
{
    /**
     * 队列监听
     */
    public static function listen(string $queueName, $callback)
    {
        Queue::consumer($queueName)->listen(function (Job $job) use ($callback) {
            $data = $job->getJobData();
            call_user_func($callback, $data);
        }, 1);
    }

    /**
     * 停止队列监听
     */
    public static function stop(string $queueName)
    {
        Queue::consumer($queueName)->stopListen();
    }
}
