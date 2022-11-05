### 定时任务

同 linux 的 Crontab。

- 在 app/Cron/ 下面创建定时任务处理程序类：

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

- 有两种方式启动定时任务：
    1. 在服务启动命令中加入`--crontab` 参数，如：`php easyswoole start --env=test --crontab`；
    2. 设置系统环境变量：`export WECARSWOOLE_CRON=on`；

注意：定时任务同 Controller 一样也是**处理程序**，不能在里面直接写业务逻辑，业务逻辑同样需要在 Domain/ 中实现。

> 注意：
> 老版本的程序是在config/config.php配置需要执行crontab的服务器：
>    ```php
>        'crontab_server' => apollo('application', 'crontab_server') ?: '',
>    ```
> 框架新版本对此种仍然做了兼容，但新的程序不要通过这个配置来设置crontab了（在容器环境下无效）。
>
> 旧版本代码如果想要使用命令行模式，需要更新 wecarswoole 框架后，在入口文件 `easyswoole` 中加入如下一段代码：
> ```php
>    // 是否要启动定时任务
>    if (in_array('--crontab', $args)) {
>        define('WECARSWOOLE_CRON', 1);
>    }
> ```


- 如何手动执行（调试）crontab 脚本：

  v1.1.32 以及以后的框架版本，在项目根目录有个 crontab 脚本文件，可以用该脚本来手动执行 crontab 脚本。

  命令：`php crontab --env=test --task=MyCron`。

  **该脚本有两个用途：**
  1. 可以通过该脚本使用 linux 的 crontab，代替 easyswoole 的 crontab（不在 config/cron.php 文件中配置 easyswoole 的 crontab，而是直接用 linux 的 crontab 执行上面的命令）；
  2. 可以使用该脚本手动执行 crontab 任务，这在调试时很有用（虽然正式的时候仍然使用 easyswoole 的 crontab）；

  **旧版本框架如何使用：**
  1. 将最新版本框架的 template/ 目录下的 crontab 文件内容拷贝到你的项目根目录中；
  2. 在项目中执行`composer update framework/wecarswoole --no-plugins` 将框架更新的最新版本；
  3. 在项目中执行 `composer update dev/mysql --no-plugins` 将 dev/mysql 库更新到最新版本（旧版本在使用 crontab 脚本时有 bug）；

[返回](../README.md)
