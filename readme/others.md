### 缺失的 Model

由于 Model 的具体含义有分歧，容易被乱用，实际中大部分时候被用作 ORM 和 DTO，并非真正意义上的"对象"（起的是数据结构作用），因而我们并没有引入 Model 的概念，而是引入 DDD 设计中的**仓储**概念。

### 缺失的 Logic

Logic 一词同样含义模糊（一切皆逻辑），而且实际使用中过于扁平化，导致代码过于臃肿。取而代之，我们引入更加具有含义、更具纵深、更加灵活的 DDD 中的**Domain**概念（业务领域），让开发者根据实际情况自己组织代码层次。

### 关于对象存储（持久化）

面向对象编程必然需要考虑对象存储问题。

最简单的方式是直接 `serialize`、`unserialize` 处理，但这样有个致命问题：它会导致类本身可重构性很差，在后期对类私有属性的改动可能会导致历史数据反序列化失败。例如：

```php
class MyClass
{
    private $province;
    private $city;
    private $area;
    
    public function __construct($province, $city, $area)
    {
        $this->province = $province;
        $this->city = $city;
        $this->area = $area;
    }
    
    public function getAddress(): string
    {
        return "{$this->province}-{$this->city}-{$this->area}";
    }
}
```

上面的类将地址的省、市、区名称作为三个字符串存在三个字段中，我们作如下创建和存储：
```php
$obj = new MyClass("广东省", "深圳市", "南山区");
$saveStr = serialize($obj);
// 将$saveStr保存到数据库中...
```

一两行代码搞定存储，看似很完美。直到在不久的将来另一批小伙伴对该类进行了重构，如下：

```php
class Address
{
    ...
    
    public function __construct($province, $city, $area)
    {
        ...
    }
    
    public function __toString()
    {
        return "{$this->province}-{$this->city}-{$this->area}";
    }
}

class MyClass
{
    private $address;
    
        /**
         * 或者改成 public function __construct(Address $address)
         */
        public function __construct($province, $city, $area)
        {
            $this->address = new Address($province, $city, $area);
        }
        
        public function getAddress(): string
        {
            return strval($this->address);
        }
}
```

将原先的省、市、区封装到新对象 Address 中。注意该重构对外是透明的（没有改变公有接口声明），然而，此时我们再反序列化前面的那个 $saveStr，然后再调 getAddress() 方法就会有问题，因为反序列化出来的对象压根没有 address 属性。

也就是说，虽然 $province、$address 等是私有属性，但对其修改仍然会造成外部影响，这是违背面向对象的封装原则的。

当然这个问题可以通过魔术方法 __wakeup() 来解决，在 __wakeup() 中根据原先的三个属性构建新的 Address 对象，问题是，这要求后面的维护人员要知道历史数据中存在这些属性，当代码几经转手后，这几乎是不可能的（或者代码变得越来越不可理解）。

框架提供了另一种（但并非完美也并非推荐）实现方式。

Wecarswoole 框架提供了`IExtractable`和`IArrayBuildable`两个接口，以及配套的`ObjectToArray`和`ArrayToObject`两个trait，用于实现对象和数组互转(在存储的时候将数组转成json存储)。

框架的这个机制和`serialize\unserialize`本质上存在一样的问题，即随着类的重构以及维护团队的变更，代码越来越不可理解，出错率越来越高。另外，框架这个机制要求所有自定义的类需要实现上面两个接口，这是不友好的。

这个问题没有银弹。所有可以带来便捷的方式（以上两种）都会带来持续重构和维护上的灾难。

##### 实践：
实际中，可以根据具体的情况选择具体的实现方式。

需考虑是将所有属性存在一个表字段中呢，还是每个属性一个表字段(或表)。这里主要从查询需求角度考虑（是不是要对属性做查询）。一般可综合平衡考虑，即部分属性放单独字段，部分属性（可能是非结构性属性）放json字符串。

为了在仓储中存取私有字段，建议可实现 `__get`、`__set` 方法（可使用框架提供的 trait `GetterSetter`，但建议仅在仓储中通过这种方式获取属性，其他地方仍然通过公有方法获取）。

一般比较简单的对象可以直接通过构造函数重建，即在仓储中直接new————特别对于值对象，如：

```php
public function getStation(int $stationId): Station
{
    ...
    $station = new Station($id, $name);
    // 可能要设置其他属性
    ...
}
```

对于复杂的方法（一般其属性不仅仅是简单类型）需要在仓储中手动构建各部件并通过 __set 组装。
```php
public function getStation(int $stationId): Station
{
    // 通过反射（框架提供的助手类Reflection）创建出对象，然后设置属性
    $station = Reflection::getReflectionClass(Station::class)->newInstanceWithoutConstructor();
    ...
    $station->id = $id;
    $station->name = $name;
}
```

之所以说以上这种原始的方式是最佳实践，因为它是可维护的、重构友好的、可读的，当类发生变更时，重构者必须（也比较容易）去变更它的构建过程（而不是让系统"错误地"包办了）。

当然，如果对象的构建确实过于复杂，可考虑引入建造者模式辅助构建。

##### 重建后的重复对象问题：
有一种场景需要考虑，比如下面`User`类的构造：

```php
class User
{
    public function __construct(Station $station, ...)
    {
        ...
    }
}
```

User 类依赖于 Station 类，在 User 的仓储中构建 User 类时，必须根据 $stationId 先构建 Station 类。如果直接构建，会每个 $user 对象都会单独构建一个 $station，虽然 $stationId 都是一样的，这会造成资源的浪费。

我们期望的是一个进程中同一个 $stationId 应该只有一个 Station 对象。

这可以通过**享元模式**解决。

框架提供的**[延迟加载代理（LazyProxy）](./lazyproxy.md)**支持此类享元模式，且提供了对象延迟加载功能，最大程度地提升对象创建性能，请参见相关章节。

### 为何 Http/Controllers 下面有 V1 这样的版本划分？

Http/Controller 是系统最主要的对外 API，API 一旦定义则很难做不兼容修改（比如改个字段名，删掉某字段，改变字段含义等），因而可以采用版本控制，对外提供不同版本的 API(这也是为何 Restful API 的 uri 中包含版本的原因)。
不同于 API，Domain 不需要版本概念，因为 Web 产品一般只会演化而不会分版本（可能会划分新模块）。当然，会有定制化需求，可以采用其他方案处理。

### 框架

一般不要用 `easyswoole stop force`，从其实现来看，会造成僵尸进程。

### 工厂

- MySQL、Redis、Email、Cache、Logger 等基础设施都有相应工厂来创建，工厂依赖于 EasySwoole（主要依赖于配置），并且将具体的基础设施扩展与 EasySwoole 框架隔离（即扩展本身不依赖于框架）。
- 工厂返回的基础设施尽量符合 PSR 规范（如 Cache、Logger 等）。
- 虽然提供了工厂，但实际使用中不建议直接用工厂获取对象（工厂并不提供单例模式），项目中请用 IoC 注入（本项目用的是 PHP-DI，建议通过构造函数注入这些基础设施）。

### 生产环境

每次发布生产后需执行：`composer dump-autoload -o` 优化自动加载速度。

### 循环引用
wecarswoole 属于常驻进程服务，需特别注意对象间的循环引用问题，这点和 fpm 模式不同。如下：

```php
class A
{
    private $b;
    
    public function __construct(B $b)
    {
        // 这里构成循环引用
        $b->setA($this);
        $this->b = $b;
    }

    public function __destruct()
    {
        echo "destr A\n";
    }

    public function close()
    {
        unset($this->b);
    }
}

class B
{
    private $a;
    
    public function setA(A $a)
    {
        $this->a = $a;
    }

    public function __destruct()
    {
        echo "destr B\n";
    }
}

// 创建对象
// 当请求结束后，$a和$b引用的两个对象都得不到释放，因为存在循环引用，两个对象的引用计数永远不会变成0，无法被回收，造成内存泄漏
$b = new B();
$a = new A($b);

// 如果确实需要循环引用（如实现双向链表），则必须手动解除引用
$a->close();
```

### readme 文案书写规范

参见[中文文案排版指北](https://github.com/sparanoid/chinese-copywriting-guidelines)。


另外，easyswoole 目前更新较快，建议大家可去看下里面提供的其他有用的功能。


[返回](../README.md)