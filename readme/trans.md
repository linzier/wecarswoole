### 事务

不要在**仓储**中使用事务（仓储要尽可能简单，引入事务会使仓储变得复杂而且很容易引入业务逻辑代码）。

不要在**控制器/handler** 中使用事务（原因同上）。

一般情况下**建议在 Service 中使用事务**（事务本身就有协调之含义。在 DDD 实践中，建议在应用服务中管理事务，不过我们为了使用上的简单性，没有引入应用服务的概念，感兴趣的同学可以自行百度了解）。

不推荐在 Entity 中使用事务，因为 Entity 需要保持类功能的单一性，引入事务往往会使一个 Entity 变得过于复杂，而且其它 Entity 或 Service 有可能调用此 Entity，会造成事务嵌套。

**事务不支持跨库。**

使用示例（以下仅作示例，并非最佳实践）：

```php
use WecarSwoole\Transaction;
...

// 创建两个仓储
$repos1 = Container::get(IUserRepository::class);
$repos2 = Container::get(IMerchantRepository::class);

// 开启事务（同时将这两个仓储加到事务中）
$trans = Transaction::begin($repos1, $repos2);

// 中间可以用 $trans->add($newRepos) 添加新仓储到事务中

// 执行各个仓储的方法
// 在执行每个仓储的方法后可以立即判断是否成功，如果失败则立即回滚，就不用再执行后面仓储的方法了
$res1 = $repos1->add(new User('13909094444'));
$res2 = $repos2->add(new Merchant(29090, 1));

// 提交或回滚
if ($res1 && $res2) {
    $trans->commit();
} else {
    $trans->rollback();
}
```

[返回](../README.md)