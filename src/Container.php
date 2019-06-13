<?php

namespace WecarSwoole;

use EasySwoole\EasySwoole\SysConst;
use EasySwoole\Component\Di;

/**
 * Container facade
 * Class Container
 * @package WecarSwoole
 */
class Container
{
    public static function get($id)
    {
        return Di::getInstance()->get(SysConst::DI_CONTAINER)->get($id);
    }

    public static function has($id)
    {
        return Di::getInstance()->get(SysConst::DI_CONTAINER)->has($id);
    }
}