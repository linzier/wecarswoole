<?php

namespace WecarSwoole\Util;

/**
 * 通过反射获取对象属性
 * Class Property
 * @package WecarSwoole\Util
 */
class Property
{
    /**
     * 获取所有的属性，返回属性名称列表
     * @param $object
     * @param array $excludes 排除某些属性
     * @param bool $onlySelf 仅获取自己的属性，不获取从基类继承来的
     * @param int $filter ReflectionProperty中的常量，参见PHP文档
     * @return array
     * @throws \ReflectionException
     */
    public static function getProperties($object, array $excludes = [], bool $onlySelf = false, int $filter = 0): array
    {
        $properties = $filter ? (new \ReflectionClass($object))->getProperties($filter) : (new \ReflectionClass($object))->getProperties();

        if ($onlySelf) {
            // 过滤掉基类的
            $className = get_class($object);
            $properties = array_filter(
                $properties,
                function (\ReflectionProperty $property) use ($className) {
                    return $property->getDeclaringClass()->getName() == $className;
                }
            );
        }

        return array_filter(
            array_map(
                function (\ReflectionProperty $property) {
                    return $property->getName();
                },
                $properties
            ),
            function (string $propetyName) use ($excludes) {
                return !$excludes || !in_array($propetyName, $excludes);
            }
        );
    }
}
