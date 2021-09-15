### 自定义进程
由于easyswoole（至少是早期版本的）的 `AbstractProcess` 有bug：对SIGTERM捕获后没有终止当前进程，导致进程无法终止，从而导致整个服务无法被SIGTERM终止，因而不建议直接继承该类创建自定义进程，而是继承修复类 `WecarAbstractProcess`，并实现 `exec($arg)` 方法。

### 使用方式
1. 在 `App/Process/` 目录下创建自定义进程，继承 `WecarAbstractProcess`，如：
   ```php
   <?php

    namespace App\Process;

    use WecarSwoole\Process\WecarAbstractProcess;

    class OrderQueueConsumer extends WecarAbstractProcess
    {
        protected function exec($arg)
        {
            // do something
        }
     
        /**
         * 可在此类增加进程退出前的逻辑
         */
        protected function onExit()
        {
           // do something
           parent::onExit();
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

    如果提供了第一个参数（进程名称），则会在storage/temp/下面写入一个"{进程名称}.txt"的文件，正常退出（kill -15)时会删除该文件。

### 进程中的无限循环
一般可以使用swoole提供的定时器来实现类似无限循环（定时执行）。

如果要自己写无限循环，则需要注意：如果循环体中没有任何协程调度的操作（如IO操作），则需要加入一个适当的sleep，否则会导致整个服务无法正常退出（kill -15不生效）。
sleep的值不能小于1毫秒，否则同样会导致无法退出。

建议使用`Coroutine::sleep(0.001);`。如：
```php
    protected function exec($arg)
    {
        while(true) {
            // do something
            
            // 1毫秒
            Coroutine::sleep(0.001);
        }
    }
```