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

    public function __construct()
    {
        $this->name = '三子';
    }

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

$arr = ['a' => new A()];
$s = json_encode($arr);
echo "enc:",$s,"\n";
$a2 = json_decode($s, true);
var_export($a2);
