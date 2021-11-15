<?php
namespace Wecarswoole\LazyLoad\Proxy\_ZSLYYDS_\HeaveClass;
use WecarSwoole\LazyProxy\IWrap;

final class TmpProxydu736 extends \HeaveClass implements IWrap {
private const REAL_CLS_NAME_SF876Y = '\HeaveClass';
private const INNER_PROPS_SF876Y = ["love" => true,];
private const MAGIC_METHODS_SF876Y = ["__construct" => true,"__call" => true,"__get" => true,"__set" => true,"__isset" => true,"__unset" => true,];
private const IS_ENTITY_SF876Y = false;
private static $eContainer_sf651l = [];
private static $initFunc_sf651l;
private $object_sf651l;
private $objectId_sf651l;
private $shareEntity_sf651l;
private $rebuild_sf651l;
private $extra_sf651l;

public function getAge(): int{
return $this->interceptor_sf651l("getAge", ...func_get_args());
}

public function __call( $name,  $arguments){
return $this->interceptor_sf651l("__call", ...func_get_args());
}

   public function __destruct()
   {
       // wraper尚未关联真实对象，或者真实对象属于独占型对象（非共享对象），不用处理
       if (!$this->object_sf651l || !$this->shareEntity_sf651l) {
           return;
       }
       
       if (!isset(self::$eContainer_sf651l[$this->objectId_sf651l])) {
           return;
       }
       
       // 将容器中该对象的wraper引用计数减一
       $cnt = self::$eContainer_sf651l[$this->objectId_sf651l][0];
       if ($cnt <= 1) {
           unset(self::$eContainer_sf651l[$this->objectId_sf651l]);
       } else {
           self::$eContainer_sf651l[$this->objectId_sf651l][0] -= 1;
       }
   }

   private function initProps_sf651l()
   {
       $props = self::getReflectionCLass()->getProperties(\ReflectionProperty::IS_PUBLIC);
       foreach ($props as $property) {
           if ($property->isStatic()) {
               continue;
           }
           unset($this->{$property->getName()});
       }
   }

    private function setObject_sf651l($object = null, $shareEntity = true)
    {
        if (is_object($object)) {
            // 对象
            $this->object_sf651l = $object;
            $this->objectId_sf651l = self::IS_ENTITY_SF876Y ? $object->id() : '';
        } elseif (is_int($object) || is_string($object) && $object) {
            // 实体标识
            if (!self::IS_ENTITY_SF876Y) {
                throw new \Exception('create proxy fail:' . self::REAL_CLS_NAME_SF876Y . ' is not entity');
            }
            $this->object_sf651l = null;
            $this->objectId_sf651l = $object; 
        } else {
            // 啥也不传，表示延迟创建普通对象
            if (self::IS_ENTITY_SF876Y) {
                // 实体必须传id
                throw new \Exception('create proxy fail:entity id required');
            }
            $this->object_sf651l = null;
            $this->objectId_sf651l = '';
        }
        
        if (!self::IS_ENTITY_SF876Y) {
            // 普通对象都采用独占式
            $this->shareEntity_sf651l = false;
        } else {
            if (
                is_object($object)
                && isset(self::$eContainer_sf651l[$object->id()])
                && spl_object_hash($object) != spl_object_hash(self::$eContainer_sf651l[$object->id()][1])
            ) {
                // 传入的对象和容器的不是一个，则本wraper采用独占式
                $this->shareEntity_sf651l = false;
            } else {
                $this->shareEntity_sf651l = $shareEntity;
            }
        }
    }

    private function setInitializer_sf651l($initFunc, $extra = [])
    {
        $this->extra_sf651l = $extra;
        
        // 构造器只需要设置一次
        if (self::$initFunc_sf651l) {
            return;
        }
        
        if ($initFunc instanceof \Closure) {
            self::$initFunc_sf651l = $initFunc;
            return;
        }
        
        if (is_callable($initFunc)) {
            self::$initFunc_sf651l = \Closure::fromCallable($initFunc);
            return;
        }
        
        if (is_callable(__CLASS__, $initFunc)) {
            self::$initFunc_sf651l = \Closure::fromCallable([__CLASS__, $initFunc]);
            return;
        }
        
        throw new \Exception("invalid init function:$initFunc");
    }

    private function trytoBuild_sf651l()
    {
        // 真实对象已经创建了
        if ($this->object_sf651l) {
            return;
        }
        
        // 创建真实对象
        // 如果是共享对象，则先检查容器中有没有现成的
        if ($this->shareEntity_sf651l && isset(self::$eContainer_sf651l[$this->objectId_sf651l])) {
            self::$eContainer_sf651l[$this->objectId_sf651l][0] += 1;
            $this->object_sf651l = self::$eContainer_sf651l[$this->objectId_sf651l][1];
            return;
        }
        
        // 构造
        $params = $this->extra_sf651l ?? [];
        if (self::IS_ENTITY_SF876Y) {
            array_unshift($params, $this->objectId_sf651l);
        }
        
        $this->object_sf651l = call_user_func(self::$initFunc_sf651l, ...$params);
        if (!$this->object_sf651l) {
            throw new \Exception('create object of ' . self::REAL_CLS_NAME_SF876Y . 'fail');
        }
        
        // 如果共享对象，则放到容器中
        if ($this->shareEntity_sf651l) {
            self::$eContainer_sf651l[$this->objectId_sf651l] = [1, $this->object_sf651l];
        }
    }

    private function interceptor_sf651l(string $methodName, ...$args)
    {
        $this->trytoBuild_sf651l();
        return call_user_func([$this->object_sf651l, $methodName], ...$args);
    }

    public function __get($name)
    {
        // 先检查属性是不是protected/private的，如果是，而类没有定义__get，则不能访问
        // 必须先做此检查，因为代理类和真实类属于继承关系，代理类天然能访问真实类的protected属性，即使真实类没有__get方法
        if (!self::canMagicOp($name, '__get')) {
            throw new \Exception("property $name of class " . self::REAL_CLS_NAME_SF876Y . ' can not access');
        }
        
        // 注意要在build后检查，因为PHP可以动态设置属性
        $this->trytoBuild_sf651l();
        if (!property_exists($this->object_sf651l, $name)) {
            throw new \Exception('class ' . self::REAL_CLS_NAME_SF876Y . ' has no property ' . $name);
        }
        return $this->object_sf651l->{$name};
    }

    public function __set($name, $value)
    {
        if (!self::canMagicOp($name, '__set')) {
            throw new \Exception("can not set property $name on class " . self::REAL_CLS_NAME_SF876Y);
        }
        
        // 注意要在build后检查，因为PHP可以动态设置属性
        $this->trytoBuild_sf651l();
        if (!property_exists($this->object_sf651l, $name)) {
            throw new \Exception('class ' . self::REAL_CLS_NAME_SF876Y . ' has no property ' . $name);
        }
        $this->object_sf651l->{$name} = $value;
    }

    private static function canMagicOp(string $propName, string $op): bool
    {
        if (!isset(self::INNER_PROPS_SF876Y[$propName])) {
            // 不是私有属性，返回true
            return true;
        }
        
        if (isset(self::MAGIC_METHODS_SF876Y[$op])) {
            // 有魔术操作方法
            return true;
        }
        
        return false;
    }

    public function __sleep()
    {
        $arr = ['objectId_sf651l', 'shareEntity_sf651l', 'rebuild_sf651l', 'extra_sf651l'];
        if (!$this->rebuild_sf651l) {
            // 如果反序列化后不重新构建真实对象，则需要在序列化之前构建好，否则可能会导致未知错误
            $this->trytoBuild_sf651l();
            $arr[] = 'object_sf651l';
        }
        
        return $arr;
    }

    public function __wakeup()
    {
        $this->initProps_sf651l();
        if (self::IS_ENTITY_SF876Y && !$this->rebuild_sf651l && $this->shareEntity_sf651l) {
            // 非重建且共享型实体对象，要处理共享对象情况
            $this->object_sf651l = $this->fetchShareEntity($this->object_sf651l);
        }
    }

    public function __clone()
    {
        // clone后需立即构建新clone的wraper里面的真实对象，否则在极端情况下可能会因所有的克隆对象都要去构建真实对象而导致严重的性能问题
        $this->trytoBuild_sf651l();
        $this->object_sf651l = clone $this->object_sf651l;
        $this->shareEntity_sf651l = false;
    }

    public function __isset($name)
    {
        if (!self::canMagicOp($name, '__isset')) {
            throw new \Exception("can not access property $name on class " . self::REAL_CLS_NAME_SF876Y);
        }
        
        $this->trytoBuild_sf651l();
        return isset($this->object_sf651l->{$name});
    }

    public function __unset($name)
    {
        if (!self::canMagicOp($name, '__unset')) {
            throw new \Exception("can not access property $name on class " . self::REAL_CLS_NAME_SF876Y);
        }
        
        $this->trytoBuild_sf651l();
        unset($this->object_sf651l->{$name});
    }

    private function fetchShareEntity($entity = null, bool $replace = true)
    {
        if (!self::IS_ENTITY_SF876Y || !$this->shareEntity_sf651l) {
            return $entity;
        }
        
        $id = $this->objectId_sf651l;
        if ($entity) {
            if (!isset(self::$eContainer_sf651l[$id])) {
                // 容器中没有共享对象，写入
                self::$eContainer_sf651l[$id] = [1, $entity];
                return $entity;
            } else {
                // 容器中有共享对象，则看是否要使用容器中的对象
                if ($replace) {
                    self::$eContainer_sf651l[$id][0] += 1;
                    return self::$eContainer_sf651l[$id][1];
                } else {
                    return $entity;
                }
            }
        } else {
            // 没有提供$entity
            if (isset(self::$eContainer_sf651l[$id])) {
                self::$eContainer_sf651l[$id][0] += 1;
                return self::$eContainer_sf651l[$id][1];
            } else {
                return null;
            }
        }
    }

    public static function createProxyPx771jdh7($object = null, array $extra = [], $initFunc = 'buildInstance', bool $shareEntity = true, bool $rebuildAfterSleep = true)
    {
        if ($object instanceof IWrap) {
            // 防止对代理对象做包装
            return $object;
        }
        
        // 创建代理对象
        $proxy = self::getReflectionCLass()->newInstanceWithoutConstructor();
        $proxy->rebuild_sf651l = $rebuildAfterSleep;
        // 初始化属性
        $proxy->initProps_sf651l();
        // 设置真实对象/标识
        $proxy->setObject_sf651l($object, $shareEntity);
        // 设置初始化器
        $proxy->setInitializer_sf651l($initFunc, $extra);
        
        return $proxy;
    }

    private static function getReflectionCLass()
    {
        static $r;
        $r = $r ?? new \ReflectionClass(__CLASS__);
        return $r;
    }}