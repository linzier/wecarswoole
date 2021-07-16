<?php

namespace WecarSwoole\Queue;

use EasySwoole\Queue\Queue as EsQueue;
use EasySwoole\Queue\QueueDriverInterface;
use WecarSwoole\Queue\Driver\RedisDriver;

class Queue
{
    private static $map = [];

    public static function instance(string $queueName, QueueDriverInterface $driver = null): EsQueue
    {
        if (!isset(self::$map[$queueName])) {
            self::$map[$queueName] = new EsQueue($driver ?? new RedisDriver($queueName));
        }

        return self::$map[$queueName];
    }
}
