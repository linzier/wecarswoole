<?php

namespace WecarSwoole\Queue\Driver;

use EasySwoole\Queue\Job;
use EasySwoole\Queue\QueueDriverInterface;
use Psr\Log\LoggerInterface;
use WecarSwoole\Container;
use WecarSwoole\RedisFactory;

/**
 * 消息队列：Redis 驱动
 */
class RedisDriver implements QueueDriverInterface
{
    protected $redis;
    protected $queueName;

    public function __construct(string $queueName, string $redisAlias = 'main')
    {
        $this->redis = RedisFactory::build($redisAlias);
        $this->queueName = $queueName;
    }
    
    public function push(Job $job): bool
    {
        return $this->redis->lPush($this->redisKey(), json_encode($job->getJobData()));
    }

    /**
     * 出列需要捕获异常，防止异常导致队列监听中断
     */
    public function pop(float $timeout = 3.0): ?Job
    {
        try {
            if ($data = json_decode($this->redis->rPop($this->redisKey()), true)) {
                $job = new Job();
                $job->setJobData($data);
                return $job;
            }
        } catch (\Exception $e) {
            Container::get(LoggerInterface::class)->critical("dequeue fail:{$e->getMessage()}");
        }

        return null;
    }

    public function size():?int
    {
        return $this->redis->lLen($this->redisKey());
    }

    protected function redisKey(): string
    {
        return "wecar-queue-{$this->queueName}";
    }
}
