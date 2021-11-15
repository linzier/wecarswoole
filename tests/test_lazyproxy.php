<?php

use function WecarSwoole\LazyProxy\proxy;

require_once './base.php';

\WecarSwoole\Bootstrap::bootForTest();

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

$a = proxy(HeaveClass::class, 'createHeaveClass', [23], false, true);
$a2 = proxy(HeaveClass::class, 'createHeaveClass', [45]);
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
//echo "ca love:", $ca->love,"\n";
//$a->love = "滑冰";
//echo "after a love:", $a->love,"\n";
//echo "after ca love:", $ca->love,"\n";
