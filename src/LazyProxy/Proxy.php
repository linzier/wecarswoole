<?php

namespace WecarSwoole\LazyProxy;

/**
 * 代理类
 * 注意：__get、__set、__unset、__isset等方法里面不支持协程切换（参见swoole文档），
 * 为避免出问题，当构造器中会发生协程切换时（如调API），尽量不要通过这些方法访问私有属性，防止多协程并发操作时出问题，
 * 而且也不要设置public属性（访问public属性时代理类也是通过__get、__set来处理的，也会导致问题）
 * Class Proxy
 * @package WecarSwoole\LazyProxy
 */
class Proxy
{
    private static $clsMap = [];

    /**
     * 给实体对象（或id）创建代理
     * @param $objOrCls Identifiable|string 实体对象或者类名，当是类名时，$extra的第一个元素必须是对象标识（id）
     * @param array|string|int $extra 创建实体对象时的额外参数，当$objOrCls是类名时，第一个参数必须是id值（如果没有其他额外参数，则可直接提供id的值而不用提供数组）
     * @param string $initFunc 对象创建器，callable或者字符串，字符串表示objClass里面的静态方法
     * @param bool $shareEntity 是否共享实体对象
     * @param bool $rebuildAfterSleep 每次反序列化后是否要通过$initFunc重建实体对象
     * @return mixed
     * @throws \Exception
     */
    public static function entity($objOrCls, $extra = [], $initFunc = 'newInstance', bool $shareEntity = true, bool $rebuildAfterSleep = false)
    {
        if (!is_array($extra) && ($extra || $extra === 0)) {
            $extra = [$extra];
        } else {
            $extra = $extra ? array_values($extra) : [];
        }

        if (is_string($objOrCls)) {
            // 提供的是类名，必须通过extra传入id
            if (count($extra) < 1 || !is_int($extra[0]) && !is_string($extra[0])) {
                throw new \Exception("create $objOrCls fail:entity id required");
            } else {
                $id = array_shift($extra);
                $extra['__id__'] = $id;
            }
        } elseif (!$objOrCls instanceof Identifiable) {
            throw new \Exception(get_class($objOrCls) . 'not implement Identifiable');
        }

        return self::wrap($objOrCls, $initFunc, $extra, $shareEntity, $rebuildAfterSleep);
    }

    /**
     * 批量创建实体代理
     * 当其中任何一个触发创建逻辑时，则调$initFunc创建全部的真实对象
     * 触发创建时机和wrap是一致的
     * 该接口只能用来创建实体，且不支持反序列化后rebuild
     * $initFunc函数格式：function(array $ids, ...$extra): Identifiable[]
     * @param string $className
     * @param array $ids
     * @param array $extra
     * @param string $initFunc
     * @param bool $shareEntity
     * @return array
     * @throws \ReflectionException
     */
    public static function batch(string $className, array $ids, $extra = [], $initFunc = 'newInstances', bool $shareEntity = true): array
    {
        if (!isset(self::$clsMap[$className])) {
            self::$clsMap[$className] = ClassGenerator::generateProxyClass($className);
        }

        return self::$clsMap[$className]::createBatchProxyPx771jdh7($ids, $extra, $initFunc, $shareEntity);
    }

    /**
     * 创建代理，可创建实体对象代理或普通对象代理
     * @param $objOrCls
     * @param $initFunc
     * @param array $extra
     * @param bool $shareEntity
     * @param bool $rebuildAfterSleep
     * @return mixed
     * @throws \Exception
     */
    public static function wrap($objOrCls, $initFunc, array $extra = [], bool $shareEntity = false, bool $rebuildAfterSleep = false)
    {
        if (is_string($objOrCls)) {
            if (!class_exists($objOrCls)) {
                throw new \Exception("class $objOrCls not found");
            }
            $cls = $objOrCls;
        } else {
            $cls = get_class($objOrCls);
        }

        if (!isset(self::$clsMap[$cls])) {
            self::$clsMap[$cls] = ClassGenerator::generateProxyClass($cls);
        }

        $id = null;
        if (isset($extra['__id__'])) {
            $id = $extra['__id__'];
            unset($extra['__id__']);
        }

        return self::$clsMap[$cls]::createProxyPx771jdh7(
            is_object($objOrCls) ? $objOrCls : $id,
            $extra,
            $initFunc,
            $shareEntity,
            $rebuildAfterSleep
        );
    }

    /**
     * 预加载代理类
     * 支持两种格式的参数：
     * 1. [string classname1, string classname2,...]，仅提供classname
     * 2. [string classname => callable, ...]，以 classname为key，构造器为value，同时提供classname和构造器
     * @param array $classNames
     * @throws \ReflectionException
     */
    public static function preload(array $classNames)
    {
        if (!$classNames) {
            return;
        }

        $hasFunc = is_string(key($classNames)) && is_callable(current($classNames));
        foreach ($classNames as $key => $val) {
            $cls = $hasFunc ? $key : $val;
            if (self::genClass($cls)) {
                $hasFunc && self::$clsMap[$cls]::setInitializerForProxy763jhfq($val);
            }
        }
    }

    /**
     * @param string $className
     * @return bool
     * @throws \ReflectionException
     */
    private static function genClass(string $className)
    {
        if (!class_exists($className)) {
            return false;
        }

        if (isset(self::$clsMap[$className])) {
            return true;
        }

        self::$clsMap[$className] = ClassGenerator::generateProxyClass($className);
        return true;
    }
}
