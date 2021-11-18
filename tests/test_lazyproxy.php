<?php

use WecarSwoole\LazyProxy\ClassGenerator;
use WecarSwoole\LazyProxy\Proxy;

require_once './base.php';

class HeaveClass
{
    public $age;
    public $time;
    protected $love;

    public function __construct(int $age)
    {
        echo "create class\n";
        $this->age = $age;
        $this->time = time();
        // 动态设置属性
        $this->something = "some other\n";
        $this->love = '篮球';
    }

    public function getAge(): int
    {
        return $this->age;
    }

    protected function say($words)
    {
        echo "say $words {$this->love}\n";
    }

    public function __call($name, $arguments)
    {
        if (method_exists($this, $name)) {
            return $this->{$name}(...$arguments);
        }
    }

    public function __get($name)
    {
        return $this->$name;
    }

    public function __set($name, $value)
    {
        $this->$name = $value;
    }

    public function __isset($name)
    {
        return isset($this->$name);
    }

    public function __unset($name)
    {
        unset($this->$name);
    }
}

function createHeaveClass(int $age)
{
    return new HeaveClass($age);
}

//$a = Proxy::wrap(HeaveClass::class, 'createHeaveClass', [23]);
//$a2 = Proxy::wrap(HeaveClass::class, 'createHeaveClass', [45]);
//echo "age:",$a->age,"\n";
//echo "age2:",$a2->age,"\n";
//echo "time:",$a->time,"\n";
//echo "time2:",$a2->time,"\n";
//$a->say('hello');
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
    private $age;
    protected $name;
    public $love;

    public function __construct($id)
    {
        echo "create HeavyEntity\n";
        // do some heavy operate
        $this->id = $id;
    }

    public function __destruct()
    {
        echo "destroy HeavyEntity\n";
    }

    public function id()
    {
        return $this->id;
    }

    public function foo(): string
    {
        return "foo {$this->name}";
    }

    public function __toString()
    {
        return "{$this->id} - {$this->age} - {$this->name}";
    }

    public static function newInstance($id): self
    {
        echo "start to create...\n";
        $o = new self($id);
        $o->love = '跑步';
        echo "create done\n";
        return $o;
    }

    public static function newInstances(array $ids): array
    {
        echo "batch create ".__CLASS__."\n";
        $arr = [];
        foreach ($ids as $id) {
            $arr[] = new self($id);
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
//    $e2 = Proxy::entity(HeavyEntity::class, 234);
//    go(function () use ($e) {
//        echo $e->foo(),"\n";
//        $e3 = Proxy::entity(HeavyEntity::class, 234);
//        echo "e3:",$e3->foo(),"\n";
//    });
////    \Swoole\Coroutine::sleep(5);
//});

//$e1 = Proxy::entity(HeavyEntity::class, 234);
//$e2 = Proxy::entity(HeavyEntity::class, 234);
//$e4 = Proxy::entity(HeavyEntity::newInstance(234));
//echo "e1 id:",$e1->id(),"\n";
//echo "e2 id:",$e2->id(),"\n";
//echo "e4 id:",$e4->id(),"\n";
//$e1->love = '足球';
//$e4->love = '篮球';
//$e2->love = '排球';
//echo "e1 love:",$e1->love,"\n";
//echo "e2 love:",$e2->love,"\n";
//$e3 = clone $e1;
//echo "e3 love:",$e3->love,"\n";
//$e1->love = '乒乓球';
//echo "e1 love:",$e1->love,"\n";
//echo "e2 love:",$e2->love,"\n";
//echo "e3 love:",$e3->love,"\n";
//unset($e1,$e2);
//echo "e3 love2:",$e3->love,"\n";
//file_put_contents("./se1.txt", serialize($e1));
//file_put_contents("./se2.txt", serialize($e2));
//file_put_contents("./se4.txt", serialize($e4));

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
$arr = Proxy::batch(HeavyEntity::class, [123, 345, 456, 567]);
$h1 = Proxy::entity(HeavyEntity::class, 123);
echo "h1 func:", $h1->id(), "\n";
foreach ($arr as $item) {
    echo $item->id(),"\n";
}
