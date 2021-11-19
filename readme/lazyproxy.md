### 延迟加载代理

#### 背景
我们在做OO开发时，有很多对象（如券Coupon）的创建本身是很重的操作，如涉及到访问数据库、远程API调用等，而在一次请求中虽然创建了这些对象，但不一定会用得上（往往是因为对象组装的需要）。

加入有如下的类 Member、Station：
```php
class Member
{
    public function __construct(Station $station)
    {
        ...
    }
    
    public function vendor(): Station
    {
        ...
    }
}
```

这里创建`Member`对象时必须传入`Station`对象，而`Station`对象的创建需查数据库（可能要涉及到多条SQL）。然而在一次请求中，可能并不需要访问这个`Station`对象，因而这些数据库查询是不必要的浪费。

一种解决方案是构造器中不传入`Station`对象，而是仅仅传入`$stationId`，内部需要的时候再去创建`Station`，然而这一方面不符合OO风格，另外导致需要在`Member`中关注`Station`的创建。

鉴于此，延迟加载代理（LazyProxy）应运而生。

（Wecarswoole的延迟加载代理有点类似开源库`ProxyManager`，不过Wecarswoole的代理仅专注于解决对象的延迟加载问题，而且在该方面功能更强大，更能满足项目实际需求）

#### 一个例子
还是拿上面的例子，看看不用代理和用代理情况下的对象创建：

不用代理：
```php
// 调仓储的方法实时创建Station对象，这里会触发查询数据库等重操作
$station = Container::get(IStationRepository::class)->getStation($stationId);
$member = new Member($station);
```

用代理：
```php
// 这里创建了个代理对象（假的、Fake对象），其对外表现和Station一模一样，但不会触发数据库查询，只有在后面真正用到$station（典型地是访问其方法）时才会触发实际的创建行为
$station = Proxy::entity(Station::class, $stationId);
$member = new Member($station);
```

### 原理
Proxy::entity($className)会给$className创建一个临时子类（代理类。在swoole服务运行中，每个类只会创建一次代理类，常驻内存），因为是继承关系，代理对象对外表现和原始对象是一模一样的，
但代理类实例化时并没有去创建原始类对象，而是拦截了原始类所有的公共属性和方法的访问（包括__开头的魔术方法），当外界访问这些属性/方法时，才去创建真正的对象。

### 原型
Proxy作为代理类入口，提供了三个方法，原型如下：
```php
namespace WecarSwoole\LazyProxy;

class Proxy
{
    /**
     * 给实体对象创建代理
     * @param $objOrCls Identifiable|string 实体对象或者类名，当是类名时，$extra的第一个元素必须是对象标识（id）
     * @param array|string|int $extra 创建实体对象时的额外参数，当$objOrCls是类名时，第一个参数必须是id值（如果没有其他额外参数，则可直接提供id的值而不用提供数组）
     * @param string $initFunc 对象构建器，callable或者字符串，可用字符串表示objClass里面的静态方法
     * @param bool $shareEntity 是否共享实体对象
     * @param bool $rebuildAfterSleep 反序列化后是否要通过$initFunc重建实体对象
     * @return mixed
     * @throws \Exception
     */
    public static function entity($objOrCls, $extra = [], $initFunc = 'newInstance', bool $shareEntity = true, bool $rebuildAfterSleep = false);
    
    /**
     * 批量创建实体代理
     * 当返回的数组中任何一个代理元素触发创建逻辑时，则调$initFunc创建全部的真实对象
     * 该接口只能用来创建实体，且不支持反序列化后rebuild
     * $initFunc函数格式：function(array $ids, ...$extra): Identifiable[]
     * @param string $className 实体类名
     * @param array $ids 待创建的实体对象id数组
     * @param array $extra 额外参数
     * @param string $initFunc 对象构建器
     * @param bool $shareEntity 是否共享实体对象
     * @return array 代理对象数组
     * @throws \ReflectionException
     */
    public static function batch(string $className, array $ids, $extra = [], $initFunc = 'newInstances', bool $shareEntity = true): array;
    
    /**
     * 创建代理，可创建实体对象代理或普通对象代理（一般用来创建普通对象代理，实体对象代理通过上面两个方法创建）
     * @param $objOrCls 对象或者类名
     * @param $initFunc 构造器
     * @param array $extra 额外参数
     * @param bool $shareEntity 是否共享实体对象（仅对实体对象有效）
     * @param bool $rebuildAfterSleep 反序列化后是否重建对象
     * @return mixed
     * @throws \Exception
     */
    public static function wrap($objOrCls, $initFunc, array $extra = [], bool $shareEntity = false, bool $rebuildAfterSleep = false);
    
    /**
     * 预加载代理类
     * 用于解决反序列化代理对象后找不到代理类的问题（因为代理类是内存中的临时类，如果服务重启了就没有了，会导致代理对象反序列化失败）
     * 支持两种格式的参数：
     * 1. [string classname1, string classname2,...]，仅提供classname
     * 2. [string classname => callable, ...]，以 classname为key，构造器为value，同时提供classname和构造器
     * @param array $classNames 需要预加载的真实类名
     * @throws \ReflectionException
     */
    public static function preload(array $classNames);
}
```

#### 说明：
- 原始类可分为两种：**实体类**和**普通类**。实体类指实现了`\WecarSwoole\LazyProxy\Identifiable`接口的类，必须提供`id()`方法，返回string|int的实体id标识。典型地，如券、油站、会员、订单、商品
等具有唯一标识的都是实体类，另外诸如配置类，虽然从DDD的概念上来说不属于实体（属于值对象），但为了能够使用代理的共享对象能力，也可以实现`Identifiable`接口成为实体。
未实现`Identifiable`接口的原始类都被视为普通类，普通类无法使用代理的共享对象和批量代理的能力。

- 实体类代理的创建方式有两种：**单一代理**和**批量代理**。单一代理通过`Proxy::entity(...)`创建，批量代理用`Proxy::batch(...)`创建。批量代理是指在创建原始对象时，调批量创建函数/方法一次性地
创建所有的原始对象，如下例所示：
```php
    use WecarSwoole\LazyProxy\Proxy;
    use App\Domain\Coupons\Coupon;
    
    // 这里有五个用户券id，需要创建五个Coupon对象。创建Coupon对象时需要调券系统API获取券信息，属于重操作。
    // 下面分别采用单一代理模式和批量代理模式创建
    $userCouponIds = [1234, 3456, 5678, 3459, 6849];
    
    // 单一代理模式
    // 这里通过array_map将$userCouponId转换成Coupon Proxy，会创建五个Proxy
    // 这五个Proxy之间没有任何关联，每个Proxy访问时都会单独调Coupon::newInstance($userCouponId)去创建Coupon对象，一共要调券系统五次，当数量多时，会造成严重的性能问题
    $coupons = array_map(
        function (int $userCouponId) {
            return Proxy::entity(Coupon::class, $userCouponId);
        },
        $userCouponIds
    );
    
    // 批量代理模式
    // 这里同样会创建五个Coupon Proxy。不同的是，这些Proxy之间是有关联的，对五个对象的访问只会触发一次构建（调Coupon::newInstances($userCouponIds)），只会调券系统一次
    $coupons = Proxy::batch(Coupon::class, $userCouponIds);
```

对于对象数组，到底是要使用批量代理还是使用单一代理，由业务实现方根据具体情况决定。
比如子系统没有提供批量创建方法，必须一次一次的调，此时批量代理和单一代理其实性能上没什么区别，批量代理可能性能更差，因为使用单一代理模式不一定需要给所有代理构建原始对象。不过此时真正
需要考虑的是让子系统提供批量查询方法，或者在批量构建方法中采用协程并发调用来优化性能。

- 实体类对象的构建分为两种：**独占型对象**和**共享型对象**。通过`$shareEntity`参数指定。
独占型对象：每个代理单独构建属于自己的原始对象。
共享型对象：相同id的实体在一个进程中只有一个对象，多个代理共享这一个对象。
普通对象都是独占型对象。
举个例子：
假如Order类和Member类都需要引用Station类：
(以下代码省略了无关的部分)
```php
...
/**
 * Station的创建需要查数据库等重操作
 */
class Station
{
...
}

class Order
{
    public function __construct(Station $station, ...)
    {
        ...
    }
}

class Member
{
    public function __construct(Station $station, ...)
    {
        ...
    }
}

```
`Order`和`Member`属于不同的模块，分别通过`MySQLOrderRepository`和`MySQLMemberRepository`获取：
```php
class MySQLOrderRepository
{
    public function getOrder(string $orderCode): ?Order
    {
        ...
        // 获取station，这里使用代理，假如$stationId = 171073
        $station = Proxy::entity(Station::class, $stationId);
        // 获取order信息
        ...
        return new Order($station, ...);
    }
}
```
```php
class MySQLMemberRepository
{
    public function getMember(int $uid): ?Member
    {
        ...
        // 获取station，这里使用代理，假如$stationId = 171073
        $station = Proxy::entity(Station::class, $stationId);
        // 获取Member信息
        ...
        return new Member($station, ...);
    }
}
```
在以上的两个仓储中，我们创建了两个Station Proxy，假如这两个Proxy各自创建一个原始对象的话，就会出现两个Station对象（id相同），这不但在性能上造成问题，在概念上也是不合理的。
所以，通过`Proxy::entity`创建的实体代理默认采用共享对象模式，此模式下，两个仓储里面的Proxy只会创建一个Station对象（其中一个会触发构建逻辑，另一个直接使用前一个构建好的那个）。