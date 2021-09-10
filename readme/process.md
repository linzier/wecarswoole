### 自定义进程
由于easyswoole（至少是早期版本的）的 `AbstractProcess` 有bug：对SIGTERM捕获后没有终止当前进程，导致进程无法终止，从而导致整个服务无法被SIGTERM终止，因而不建议直接继承该类创建自定义进程，而是继承修复类 `WecarAbstractProcess`，并实现 `exec($arg)` 方法。

### 使用方式：
1. 在 `App/Process/` 目录下创建自定义进程，继承 `WecarAbstractProcess`，如：
   ```php
   <?php

    namespace App\Process;

    use WecarSwoole\Process\WecarAbstractProcess;
    use App\Bootstrap;

    class OrderQueueConsumer extends WecarAbstractProcess
    {
        protected function exec($arg)
        {
            // 执行进程启动初始化脚本
            Bootstrap::boot();
            // do something
        }
     
        /**
         * 可在此类增加进程退出前的逻辑
         */
        protected function onExit()
        {
           // do something
        }
    }
    ```
2. 在`EasySwooleEvent::mainServerCreate()`中加入该自定义进程：
    ```php
    public static function mainServerCreate(EventRegister $register)
    {
        ServerManager::getInstance()->getSwooleServer()->addProcess((new OrderQueueConsumer())->getProcess());   
    }
    ```
    
    注意：默认自定义进程没有协程化，里面不能直接执行协程函数（如Coroutine::sleep(1))，想要协程化，得这样：
    `ServerManager::getInstance()->getSwooleServer()->addProcess((new OrderQueueConsumer('队列消费者', [], false, 2, true))->getProcess());`
    其中第五个参数true表示协程化。