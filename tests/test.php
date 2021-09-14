<?php

use WecarSwoole\Client\Response;

include_once './base.php';

class A
{
    public static function say()
    {
        echo "hello say\n";
    }
}

$c = new ReflectionClass(A::class);
$c->getMethod('say')->invoke(null);

