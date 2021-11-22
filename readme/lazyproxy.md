### 延迟加载代理

#### 背景
我们在做OO开发时，有很多对象（如券Coupon）的创建本身是很重的操作，如涉及到访问数据库、远程API调用等，而在一次请求中虽然创建了这些对象，但不一定会用得上（往往是因为对象组装的需要）。

假如有如下的类 Member、Station：
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

### 详述：
- 原始类可分为两种：**实体类**和**普通类**。实体类指实现了`\WecarSwoole\LazyProxy\Identifiable`接口的类，必须提供`id()`方法，返回string|int的实体id标识。典型地，如券、油站、会员、订单、商品
等具有唯一标识的都是实体类，另外诸如配置类，虽然从DDD的概念上来说不属于实体（属于值对象），但为了能够使用代理的共享对象能力，也可以实现`Identifiable`接口。
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

- 关于克隆。clone操作会将原始对象clone一份出来（如果没有构建则先构建），并导致wraper变成独占型、单一代理模式（如果之前是共享型、批量代理模式的话）————这是符合clone语义的。

- 关于序列化。如果参数`$rebuildAfterSleep`为true，表示反序列化后需重新构建对象，一般是为了保证获取到的对象（如券）和原始数据（如券子系统）保持一致。当该参数为true时，序列化的时候并不会保存
原始对象（即使已经构建了），反序列化后，在必要的时候会重新构建原始对象。默认情况下创建的代理该值都是false。如果`$rebuildAfterSleep`为false，则将原始对象一并序列化（如果没有则先构建）。

- 关于魔术方法__get、__set、__isset、__unset。由于PHP实现机制原因（参见swoole文档的说明），swoole在这些魔术方法中无法切换协程，而代理类拦截了对原始对象所有属性（包括public属性）的访问，
用以在必要的时候构建真实对象（即在这些方法中调构建函数创建原始对象）。另一方面，我们之所以使用代理，就是为了解决对象创建的性能问题，这意味着基本上构建函数里面都会发生协程切换（一般是读文件、查
数据库、调API等），因而这会导致问题，具体来说是当两个协程同时访问同一个代理对象的属性时，其中一个协程会调起以上魔术方法并触发对象构建，另一个协程会报"Undefined property"错误。
**因而，当要使用Proxy时，原始对象不要定义public属性，而是全部通过方法对外暴露操作，这也符合OO原则**。

- 关于构建器。有两种构建器：单一对象构建器和批对象构建器，两者原型：
```php
// 单一对象构建器：实体对象
function funcname($id, ...$extra): Object;

// 单一对象构建器：普通对象
function funcname(...$extra);

// 批对象构建器
function funcname(array $ids, ...$extra);
```

默认情况下（也是推荐的方式。对于实体来说，真正的构造逻辑一般在仓储或者Service中，实体类中提供静态方法作为代理的调用入口），认为原始类提供了相应的静态方法作为构造器（单一构造器默认叫`newInstance`，批对象构造器默认叫`newInstances`），此时调Proxy相关方法时不用显式设置构造器。

当然，如果不想遵循默认规则，可以在调用时自己指定构造器，只要传入合法的可调用结构即可（is_callable判断返回true）。注意在反序列化的时候要保证构造器是可调用的（相关类或函数已载入到内存）。

- 协程安全性。该代理是可以在多协程并发环境使用的，代理内部能保证只有一个协程触发构建逻辑，其他协程发现已经有别的协程在构建，则会等待构建结果，而不是再去发起构建。

### 综合实例
下面通过几个例子讲解机制。
```php
/**
 * 普通类
 */
class HeaveClass
{
    private $age;
    private $name;
    private $time;
    private $love;

    public function __construct(int $age, string $name)
    {
        echo "build " . __CLASS__ . "\n";
        $this->age = $age;
        $this->name = $name;
        $this->time = time();
        // 动态设置属性
        $this->something = "some other\n";
        $this->love = '篮球';
    }
    
    public function say($words)
    {
        echo "name:{$this->name},age:{$this->age},love:{$this->love} say $words,and do other:{$this->something}\n";
    }
}

/**
 * 对象构造器
 */
function createHeaveClass(int $age = 100, string $name = '')
{
    // 做一些耗时的操作...
    return new HeaveClass($age, $name);
}

// 创建对象代理，此时不会触发构建
$a1 = Proxy::wrap(HeaveClass::class, 'createHeaveClass', [23, '张三']);
// 调对方方法，此时会调createHeaveClass触发构建
$a1->say('hello');


/**
 * 重实体
 */
class HeavyEntity implements \WecarSwoole\LazyProxy\Identifiable
{
    private $id;
    private $name;
    public $love;

    public function __construct($id, $name = '')
    {
        echo "create HeavyEntity\n";
        // do some heavy operate
        $this->id = $id;
        $this->name = $name ?: 'unknow';
    }

    public function setName($val)
    {
        $this->name = $val;
    }
    
    public function getName(): string
    {
        return $this->name;
    }

    public function id()
    {
        return $this->id;
    }

    public function foo(): string
    {
        return "I am {$this->name}";
    }

    public function __toString()
    {
        return "{$this->id} - {$this->name}";
    }

    /**
     * 单一对象构建器
     * @param $id
     * @return HeavyEntity
     */
    public static function newInstance($id, $name = 'unknow'): self
    {
        echo "start to create...\n";
        // 做一些耗时操作
        Swoole\Coroutine::sleep(1);
        $o = new self($id, $name);
        $o->love = '跑步';
        return $o;
    }

    /**
     * 批对象构建器
     * @return array
     */
    public static function newInstances(array $ids, $namePrefix = ''): array
    {
        echo "batch create ".__CLASS__."\n";
        // 做一些耗时操作
        $arr = [];
        foreach ($ids as $id) {
            $arr[] = new self($id, $namePrefix . '-' . $id);
        }

        return $arr;
    }
}

// 创建两个id相同的实体，默认将采用共享对象模式
$e1 = Proxy::entity(HeavyEntity::class, 234);
$e2 = Proxy::entity(HeavyEntity::class, 234);
echo "e1 id:",$e1->id(),"\n";// 调实体代理的id()方法不会触发构建逻辑，而是直接返回id
echo "e2 id:",$e2->id(),"\n";
echo "e1 foo:", $e1->foo(), "\n";// 调实体的方法，触发构建逻辑
echo "e2 foo:", $e2->foo(), "\n";// $e1和$e2共享实体对象，上面已经构建了实体对象，此处不再构建，直接使用

// 模拟多协程并发
go(function (){
    $e0 = $e1 = null;
    // 两个协程并发创建并使用共享对象型代理，对象id相同
    // 此时只有前面一个协程会触发构建流程，后面的协程会等待构建
    for ($i = 0; $i < 2; $i++) {
        go(function () use ($i, &$e0, &$e1) {
            ${'e'.$i} = Proxy::entity(HeavyEntity::class, [234, '张三' . $i]);
            // 只有第一个协程会触发构建
            echo ${'e'.$i}->foo(),"\n";
        });
    }
});

// 要求反序列化后重建实体对象
// 注意：由于指定了$shareEntity=true，反序列化后重建的对象仍然为共享对象（即使不重建而是直接反序列化出来的实体对象也仍然是共享对象，反序列化后是否重建对是否共享对象没有影响）
$e3 = Proxy::entity(HeavyEntity::class, [678, '张三'], 'newInstance', true, true);
// 将name设置为李四
$e3->setName('李四');
// 序列化保存
file_put_contents('./heavy_entity.txt', serialize($e3));

// 在其他地方反序列化
// 由于代理类是临时类，需要先预加载，否则内存中可能没有该类
// 注意：实际实验中，如果实在同一个进程中测试的，则打出来的结果可能还是李四，因为共享对象优先从共享池中获取对象（即获取到原来那个对象了）
Proxy::preload([HeavyEntity::class]);
$e4 = unserialize(file_get_contents('./heavy_entity.txt'));
// 由于反序列化后需重建对象，下面打印出来name仍然是张三
echo $e4->getName();// 打印"张三"


// 批对象
$arr = Proxy::batch(HeavyEntity::class, [123, 345, 456, 567], ['批对象']);
$h1 = Proxy::entity(HeavyEntity::class, 123);// 该代理和批对象列表中的123那个代理共享实体对象
$ch1 = clone $h1;// clone触发123实体的构建（注意是由clone后的新对象触发的），会将该实体放入共享池中，然后再通过clone将实体分离出来给克隆对象，最后将clone的新代理对象设置为独占型代理
foreach ($arr as $item) {
    // 访问实体方法会触发构建逻辑
    // 注意第一个代理(id=123)会直接从共享池中获取到实体对象，所以它不会触发构建
    // 真正触发构建的是第二个代理（id=345），该代理调HeavyEntity::newInstances(...)方法构建出所有实体对象并放入批容器中
    // 后面的批对象代理（id为456、567）不会再触发构建（即使是在协程并发环境下），而是直接使用前者构建的结果
    echo $item->foo(),"\n";
}
// 设置ch1(克隆出来的新对象)的name不会影响$h1的
$ch1->setName("李四");
echo "h1 func：",$h1->foo(),"\n";

// 以上代码打印：
start to create...
create HeavyEntity
I am unknow
batch create HeavyEntity
create HeavyEntity
create HeavyEntity
create HeavyEntity
create HeavyEntity
I am 批对象-345
I am 批对象-456
I am 批对象-567
h1 func：I am unknow


// 直接用Proxy包装对象
// 这种用法比较少用，主要用来针对这种场景：程序中已经创建了原始对象，我们想对该对象做序列化存储，而且希望反序列化后重建该对象
// 这种包装的目的就是防止在反序列化后立即重建，而是将重建时机延迟到真正需要的时候

// 创建实体对象
// 省略构建实体过程...
$entity = new HeavyEntity();
$eProxy = Proxy::entity($entity);// 参见方法说明，可以设置额外参数
// 序列化...

// 创建普通对象
$obj = new HeavyClass();
$oProxy = Proxy::wrap($obj, 'createHeaveClass');// 参见方法说明，可设置额外参数
// 序列化...
```


### 最佳实践
一般情况下我们都是给实体对象（如券）加一层代理，建议实体对象除了实现`Identifiable`外，还提供`newInstance`和`newInstances`两个静态方法作为构造器，这样在创建代理时就不用再指定构造器了。这两个构造器中一般通过仓储来实际创建实体对象。


