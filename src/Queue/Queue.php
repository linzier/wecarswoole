<?php

namespace WecarSwoole\Queue;

use EasySwoole\Queue\Consumer;
use EasySwoole\Queue\Producer;
use EasySwoole\Queue\Queue as EsQueue;
use WecarSwoole\Queue\Driver\RedisDriver;

class Queue
{
    private static $map = [];

    public static function consumer(string $queueName): Consumer
    {
        if (!isset(self::$map[$queueName])) {
            self::$map[$queueName] = (new EsQueue(new RedisDriver($queueName)))->consumer();
        }

        return self::$map[$queueName];
    }

    public static function producer(string $queueName): Producer
    {
        if (!isset(self::$map[$queueName])) {
            self::$map[$queueName] = (new EsQueue(new RedisDriver($queueName)))->producer();
        }

        return self::$map[$queueName];
    }
}
