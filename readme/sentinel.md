### 什么是哨兵
框架提供了哨兵，用于实现诸如队列监控等功能。哨兵在单独的自定义进程中执行，可以设置只在某一台服务器上执行（通过配置指定服务器ip），也可以指定在所有服务器上执行。
默认是只在一台上执行，必须配置服务器ip才会启动。

可以设置多个哨兵，如其中一个仅在主服上执行（如监控队列健康），另一个在所有服务器上执行（如需执行清空目录的任务）。

### 用法

1. 在`EasySwooleEvent::mainServerCreate()`中添加哨兵：
```php
public static function mainServerCreate(EventRegister $register)
{
    ...
    $sentinel = new Sentinel('哨兵1');
    // onlyOnMaster默认是true（表示仅在主服一台上面执行），可以不调
    // addTask可以进行链式调用添加多个任务，其第一个参数表示多长时间执行一次（秒）
    $sentinel->onlyOnMaster(true)->addTask(4, function () {
        // 添加队列监控的哨兵任务
        QueueMonitor::watch('下单中台', ['trans_order_fin']);
    });
    ServerManager::getInstance()->getSwooleServer()->addProcess($sentinel->getProcess());
}
```

2. 如果哨兵是 onlyOnMaster 的，则有两种方式启动哨兵：
    1. 命令行启动时加 `--master` 参数，如 `php easyswoole start --env=test --master`；
    2. 设置系统环境变量：`export WECARSWOOLE_MASTER=1`；
    
3. 哨兵启动后，会在 `storage/temp/` 下面生成 `sentinel.txt` 文件；

框架提供了`QueueMonitor`哨兵供监控队列，项目根据需要使用。

> 注意：
> 旧版本中是通过在配置中心配置用于执行哨兵的服务器ip（针对 onlyOnMaster 的哨兵）：
>   ```php
>   ...
>   return [
>       'sentinel_server' => apollo('application', 'sentinel_server') ?: '',// 执行哨兵的服务器
>   ];
>   ```
> 框架对此仍然做了兼容，但新版本程序不要再使用此配置，该模式在容器下无效。
