<?php

namespace WecarSwoole\LazyProxy;

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
function entityProxy($objOrCls, $extra = [], $initFunc = 'buildInstance', bool $shareEntity = true, bool $rebuildAfterSleep = true)
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

    return proxy($objOrCls, $initFunc, $extra, $shareEntity, $rebuildAfterSleep);
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
function proxy($objOrCls, $initFunc, array $extra = [], bool $shareEntity = false, bool $rebuildAfterSleep = false)
{
    // 已经创建了代理类的类映射
    static $clsMap = [];

    if (is_string($objOrCls)) {
        if (!class_exists($objOrCls)) {
            throw new \Exception("class $objOrCls not found");
        }
        $cls = $objOrCls;
    } else {
        $cls = get_class($objOrCls);
    }

    if (!isset($clsMap[$cls])) {
        $clsMap[$cls] = ClassGenerator::generateProxyClass($cls);
    }

    $id = null;
    if (isset($extra['__id__'])) {
        $id = $extra['__id__'];
        unset($extra['__id__']);
    }

    return $clsMap[$cls]::createProxyPx771jdh7(
        is_object($objOrCls) ? $objOrCls : $id,
        $extra,
        $initFunc,
        $shareEntity,
        $rebuildAfterSleep
    );
}
