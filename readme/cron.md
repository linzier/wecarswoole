### 定时任务

同 linux 的 Crontab。

- 在config/config.php配置需要执行crontab的服务器
    ```php
        'crontab_server' => apollo('application', 'crontab_server') ?: '',
    ```

- 类创建。在 app/Cron/ 下面创建定时任务处理程序类：

  ```php
  namespace App\Cron;
  
  use EasySwoole\EasySwoole\Crontab\AbstractCronTask;
  
  class TestCron extends AbstractCronTask
  {
      public static function getRule(): string
      {
          return '*/1 * * * *';
      }
  
      public static function getTaskName(): string
      {
          return 'test cron';
      }
  
      // 注意：easyswoole 3.3.0 之前的版本后后面的版本此处的参数列表不一样，详情见官网。下面是 3.3.0 前的写法
      static function run(\swoole_server $server, int $taskId, int $fromWorkerId, $flags = null)
      {
          echo "test cron run logic\n";
      }
  }
  ```

- 在 config/cron.php 中配置：
     ```php
    ...
    return [
        TestCron::class,
    ];
     ```

> 注意：定时任务同 Controller 一样也是**处理程序**，不能在里面直接写业务逻辑，业务逻辑同样需要在 Domain/ 中实现。


[返回](../README.md)