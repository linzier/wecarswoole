<?php

namespace WecarSwoole\Queue;

use EasySwoole\Queue\Queue as EsQueue;
use WecarSwoole\Queue\Driver\RedisDriver;

class Queue
{
    private static $map = [];

    public static function instance(string $queueName): EsQueue
    {
        if (!isset(self::$map[$queueName])) {
            self::$map[$queueName] = new EsQueue(new RedisDriver($queueName));
        }

        return self::$map[$queueName];
    }
}
