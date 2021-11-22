<?php

namespace WecarSwoole\Util;

class Reflection
{
    private static $clsMap = [];

    /**
     * 获取反射类对象
     * 由于反射类的创建属于重操作，而且框架很多地方需要用到反射，此处采用统一注册表式创建，如果已经存在则不再构建
     * @param $objOrCls string|object 类全名或者对象
     * @return \ReflectionClass
     * @throws \Exception
     */
    public static function getReflectionClass($objOrCls): \ReflectionClass
    {
        $cls = is_string($objOrCls) ? $objOrCls : get_class($objOrCls);
        if (isset(self::$clsMap[$cls])) {
            return self::$clsMap[$cls];
        }

        if (!class_exists($cls)) {
            throw new \Exception("class $cls not found");
        }

        $r = new \ReflectionClass($cls);
        self::$clsMap[$cls] = $r;

        return $r;
    }
}
