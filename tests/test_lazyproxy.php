<?php

use WecarSwoole\LazyProxy\ClassGenerator;
use WecarSwoole\LazyProxy\Proxy;

require_once './base.php';

class HeaveClass
{
    private $age;
    private $name;
    private $time;
    private $love;

    public function __construct(int $age)
    {
        echo "build " . __CLASS__ . "\n";
        $this->age = $age;
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

function createHeaveClass(int $age)
{
    // 做一些耗时的操作...
    return new HeaveClass($age);
}

//$a1 = Proxy::wrap(HeaveClass::class, 'createHeaveClass', [23, '张三']);
//echo "time:",$a->time,"\n";
//echo "time2:",$a2->time,"\n";
//$a1->say('hello');
//echo $a->something,"\n";// 动态属性
//$a->love = '足球';
//unset($a->love);
//echo $a->love,"---\n";
//$sa = serialize($a);
//$sa2 = serialize($a2);
////sleep(2);
//$aa = unserialize($sa);
//$aa2 = unserialize($sa2);
//echo "after age:", $aa->age,"\n";
//echo "after age2:", $aa2->age,"\n";
//echo "after time:", $aa->time,"\n";
//echo "after time2:", $aa2->time,"\n";
//$ca = clone $a;
//echo "a love:", $a->love,"\n";
//echo "clone ca love:", $ca->love,"\n";
//$a->love = "滑冰";
//echo "after a love:", $a->love,"\n";
//echo "clone after ca love:", $ca->love,"\n";


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
    public static function newInstance($id, $name = ''): self
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

//function createEntity($id): HeavyEntity
//{
//    return new HeavyEntity($id);
//}

//go(function () {
//    $e = Proxy::entity(HeavyEntity::class, 234);
//    go(function () use ($e) {
//        echo $e->love,"\n";
//    });
//    go(function () use ($e) {
//        echo $e->love,"\n";
//    });
////    \Swoole\Coroutine::sleep(5);
//});

//$e1 = Proxy::entity(HeavyEntity::class, [234, '张三']);
//$e2 = Proxy::entity(HeavyEntity::class, [234, '张三']);
//echo "e1 id:",$e1->id(),"\n";
//echo "e2 id:",$e2->id(),"\n";
//echo "e1 foo:", $e1->foo(), "\n";
//echo "e2 foo:", $e2->foo(), "\n";
//file_put_contents("./se1.txt", serialize($e1));
//file_put_contents("./se2.txt", serialize($e2));
//file_put_contents("./se4.txt", serialize($e4));

//go(function (){
//    $e0 = $e1 = null;
//    for ($i = 0; $i < 2; $i++) {
//        go(function () use ($i, &$e0, &$e1) {
//            ${'e'.$i} = Proxy::entity(HeavyEntity::class, [234, '张三' . $i]);
//            echo ${'e'.$i}->foo(),"\n";
//        });
//    }
//});

//go(function () {
////    $e3 = Proxy::entity(HeavyEntity::class, [678, '张三'], 'newInstance', true, true);
////    $e3->setName('李四');
////    file_put_contents('./heavy_entity.txt', serialize($e3));
//    Proxy::preload([HeavyEntity::class]);
//    $e4 = unserialize(file_get_contents('./heavy_entity.txt'));
//// 由于反序列化后需重建对象，下面打印出来name仍然是张三
//    echo $e4->getName();// 打印"张三"
//});

//echo "ue4 love:",$ue4->love,"\n";
//$ue2->love = '搏击';
//echo "ue4 love:",$ue4->love,"\n";
//
//Proxy::preload([HeavyEntity::class]);
//$ue1 = unserialize(file_get_contents('./se1.txt'));
//$ue2 = unserialize(file_get_contents('./se1.txt'));
//$ue4 = unserialize(file_get_contents('./se1.txt'));
//echo "ue1 love:",$ue1->love,"\n";
//echo "ue2 love:",$ue2->love,"\n";
//echo "ue4 love:",$ue4->love,"\n";
//$e5 = Proxy::entity(HeavyEntity::class, 234);
//$e5->love = '爬山';
//echo "ue1 love:",$ue1->love,"\n";
////
////file_put_contents("./se1.txt", serialize($ue1));
////file_put_contents("./se2.txt", serialize($ue2));
////file_put_contents("./se4.txt", serialize($ue4));
//
//$arr = [12, 3, 4432, 3];

//$s = serialize($ch);

//echo \Swoole\Coroutine::getCid();

//ClassGenerator::generateProxyClass(HeavyEntity::class);


/**@var $arr HeavyEntity[] **/
go(function () {
    $arr = Proxy::batch(HeavyEntity::class, [123, 345, 456, 567], ['批对象']);
    $h1 = Proxy::entity(HeavyEntity::class, 123);
    $ch1 = clone $h1;
    foreach ($arr as $item) {
        echo $item->foo(),"\n";
    }
    $ch1->setName("李四");
    echo "h1 func：",$h1->foo(),"\n";
});
