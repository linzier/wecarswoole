### 什么是哨兵
框架提供了哨兵，用于实现诸如队列监控等功能。哨兵在单独的自定义进程中执行，可以设置只在某一台服务器上执行（通过配置指定服务器ip），也可以指定在所有服务器上执行。
默认是只在一台上执行，必须配置服务器ip才会启动。

可以设置多个哨兵，如其中一个仅在主服上执行（如监控队列健康），另一个在所有服务器上执行（如需执行清空目录的任务）。

### 用法
1. 在配置中心配置用于执行哨兵的服务器ip（如果所有的哨兵都需要在所有服务器执行，测无需配置）：
```php
...
return [
    'sentinel_server' => apollo('application', 'sentinel_server') ?: '',// 执行哨兵的服务器
];
```
2. 在`EasySwooleEvent::mainServerCreate()`中添加哨兵：
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

框架提供了`QueueMonitor`哨兵供监控队列，项目根据需要使用。