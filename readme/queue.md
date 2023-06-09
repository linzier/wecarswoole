### 队列：
Wecarswoole 基于 easyswoole/queue 组件实现了队列，使用 redis 实现队列。

### 使用：
- 入列：
    ```php
    use EasySwoole\Queue\Job;
    ...
    $job = new Job();
    $job->setJobData(['task_id' => 123456, 'enqueue_time' => time()]);
    Queue::producer($queueName)->push($job);
    ```
- 出列（监听）：
  ```php
  // 在 EasySwooleEvent 类中：
  class EasySwooleEvent implements Event
  {
      ...
      public static function mainServerCreate(EventRegister $register)
      {
        ...
        // worker 进程启动脚本
        $register->add(EventRegister::onWorkerStart, function ($server) {
            ...

            // 启动队列监听（仅在 worker 进程启动）
            if (!$server->taskworker) {
                // worker退出时停止监听
                ExitHandler::addHandler(function () {
                    QueueListener::stop($queueName);
                });

                // 监听
                QueueListener::listen($queueName, function ($data) {
                    try {
                        // do something
                    } catch (\Throwable $e) {
                        
                    }
                });
            }
        });
      }
  }
  ```
