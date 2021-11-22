<?php

namespace Test;
use Swoole\Coroutine as Co;
use WecarSwoole\ATO\ArrayToObject;
use WecarSwoole\OTA\ObjectToArray;

require_once './base.php';

class A
{
    use ArrayToObject, ObjectToArray;

    private $name;
    private $age;

    protected function __afterBuildFromArray(array $data)
    {
        $this->age = 345;
    }

    protected function __afterToArray(array $data): array
    {
        unset($data['age']);
        return $data;
    }

    public function say()
    {
        echo "$this->name,$this->age\n";
    }
}

$a = A::buildFromArrayS(['name' => '李四']);
$a->say();
$arr = $a->toArray();
var_export($arr);