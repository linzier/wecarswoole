<?php

namespace Test;
use Swoole\Coroutine as Co;
use WecarSwoole\ATO\ArrayToObject;
use WecarSwoole\OTA\ObjectToArray;
use WecarSwoole\Util\GetterSetter;

require_once './base.php';

class A
{
    private $name;
    private $age;

    public function __construct($name, $age)
    {
        $this->name = $name;
        $this->age = $age;
    }

    public function say()
    {
        echo "$this->name,$this->age\n";
    }
}

$a = new A('张三', 34);

$r = new \ReflectionClass(A::class);
$p = $r->getProperty('name');

$r->getDocComment();

$p->setAccessible(true);
echo $p->getValue($a);
