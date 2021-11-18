<?php
namespace Wecarswoole\LazyLoad\Proxy\_ZSLYYDS_\HeavyEntity;
use WecarSwoole\LazyProxy\IWrap;

final class TmpProxydu736 extends \HeavyEntity implements IWrap {
private const REAL_CLS_NAME_SF876Y = '\HeavyEntity';
private const LOCK_KEY_SF876Y = '__lockkey6q901m19a8__';
private const DEL_KEY_SF876Y = '__delkey6q901m19a8__';
private const INNER_PROPS_SF876Y = ["id" => true,"age" => true,"name" => true,];
private const MAGIC_METHODS_SF876Y = ["__construct" => true,"__destruct" => true,"__toString" => true,];
private const IS_ENTITY_SF876Y = true;
private static $eContainer_sf651l = [];
private static $relTmpContainer_sf651l = [];
private static $initFunc_sf651l;
private static $batchInitFunc_sf651l;
private $initFunc2_sf651l;
private $batchInitFunc2_sf651l;
private $object_sf651l;
private $objectId_sf651l;
private $relId_sf651l;
private $relObjIds_sf651l;
private $shareEntity_sf651l;
private $rebuild_sf651l;
private $extra_sf651l;
private $batchExtra_sf651l;
private $lockCh_sf651l;

public function id(){
return $this->interceptor_sf651l("id", ...func_get_args());
}

public function foo(): string{
return $this->interceptor_sf651l("foo", ...func_get_args());
}

public function __toString(){
return $this->interceptor_sf651l("__toString", ...func_get_args());
}

   public function __destruct()
   {
       $this->clearBatchObject_sf651l();
       $this->clearShareObject_sf651l();
   }

    private function clearShareObject_sf651l()
    {
        if (!$this->shareEntity_sf651l || !isset(self::$eContainer_sf651l[$this->objectId_sf651l])) {
            return;
        }
        
        // 共享对象容器是空的，销毁
        if (empty(self::$eContainer_sf651l[$this->objectId_sf651l])) {  
            unset(self::$eContainer_sf651l[$this->objectId_sf651l]);
            return;
        }
        
        // 将共享对象容器中该对象的wraper引用计数减一
        $cnt = self::$eContainer_sf651l[$this->objectId_sf651l]['cnt'];
        if ($cnt <= 1) {
            unset(self::$eContainer_sf651l[$this->objectId_sf651l]);
        } else {
            self::$eContainer_sf651l[$this->objectId_sf651l]['cnt'] -= 1;
        }
    }

    private function clearBatchObject_sf651l()
    {
        if (!$this->isBatchObject_sf651l() || !isset(self::$relTmpContainer_sf651l[$this->relId_sf651l])) {
            return;
        }
        
        $c = self::$relTmpContainer_sf651l[$this->relId_sf651l];
        
        // 将本对象id放入删除列表中
        if (!isset($c[self::DEL_KEY_SF876Y])) {
            $c[self::DEL_KEY_SF876Y] = [];
        }
        $c[self::DEL_KEY_SF876Y][$this->objectId_sf651l] = 1;
        
        // 如果所有对象都全部销毁了，则销毁容器
        if (count($c[self::DEL_KEY_SF876Y]) == count($this->relObjIds_sf651l)) {
            unset(self::$relTmpContainer_sf651l[$this->relId_sf651l]);
            return;
        }
        
        // 如果临时容器中存在本wraper的真实对象，则销毁
        if (isset($c[$this->objectId_sf651l])) {
            unset($c[$this->objectId_sf651l]);
        }
        
        self::$relTmpContainer_sf651l[$this->relId_sf651l] = $c;
    }

    public static function setInitializerForProxy763jhfq(string $initFunc)
    {
        if (self::$initFunc_sf651l || !is_callable($initFunc)) {
            return;
        }
        
        self::$initFunc_sf651l = $initFunc;
    }

   private function initProps_sf651l()
   {
       $props = self::getReflectionCLass_sf651l()->getProperties(\ReflectionProperty::IS_PUBLIC);
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
            if (is_object($object)) {
                if (
                    isset(self::$eContainer_sf651l[$object->id()])
                    && spl_object_hash($object) != spl_object_hash(self::$eContainer_sf651l[$object->id()]['obj'])
                ) {                                    
                    // 传入的对象和共享对象容器中的不是一个，则本wraper采用独占式
                    $this->shareEntity_sf651l = false;
                } else {
                    if (!isset(self::$eContainer_sf651l[$object->id()]) && $shareEntity) {
                        // 共享该对象
                        self::$eContainer_sf651l[$object->id()] = ['cnt' => 1, 'obj' => $object];
                    }
                    $this->shareEntity_sf651l = $shareEntity;
                }
            } else {
                $this->shareEntity_sf651l = $shareEntity;
            }
        }
        
        // 如果是共享对象，则要初始化共享对象容器
        if ($this->shareEntity_sf651l && !isset(self::$eContainer_sf651l[$this->objectId_sf651l])) {
            self::$eContainer_sf651l[$this->objectId_sf651l] = [];
        }
    }

    private function setSingleInitializer_sf651l($initFunc, $extra = [])
    {
        $this->extra_sf651l = $extra;
        
        // 构造器只需要设置一次
        if (self::$initFunc_sf651l) {
            return;
        }
        
        if (is_callable($initFunc)) {
            self::$initFunc_sf651l = $initFunc;
            return;
        }

        if (is_callable(__CLASS__, $initFunc)) {
            self::$initFunc_sf651l = [__CLASS__, $initFunc];
            return;
        }
        
        throw new \Exception("invalid init function:$initFunc");
    }

    private function setBatchInitializer_sf651l($initFunc, $extra = [])
    {
        $this->batchExtra_sf651l = $extra;
        
        // 构造器只需要设置一次
        if (self::$batchInitFunc_sf651l) {
            return;
        }
        
        if (is_callable($initFunc)) {
            self::$batchInitFunc_sf651l = $initFunc;
            return;
        }

        if (is_callable(__CLASS__, $initFunc)) {
            self::$batchInitFunc_sf651l = [__CLASS__, $initFunc];
            return;
        }
        
        throw new \Exception("invalid init function:$initFunc");
    }

    private function tryToCreateLock_sf651l()
    {
        if (\Swoole\Coroutine::getCid() == -1) {
            return null;
        }
        
        if ($lock = $this->getLock_sf651l()) {
            return $lock;
        }
        
        return $this->setLock_sf651l();
    }

    private function lock_sf651l()
    {
        if (!$lock = $this->tryToCreateLock_sf651l()) {
            return true;
        }
        
        // 这里无需区分到底是因为通道关闭了、还是超时了、还是通道空了返回的
        return $lock->push(1, 5);
    }

    private function unlock_sf651l()
    {
        if (!$lock = $this->getLock_sf651l()) {
            return;
        }
        
        // 关闭通道，唤醒所有等待者
        $lock->close();
    }

    private function getLock_sf651l()
    {
        // 如果是批对象，则是批对象锁
        if ($this->isBatchObject_sf651l()) {
            return self::$relTmpContainer_sf651l[$this->relId_sf651l][self::LOCK_KEY_SF876Y] ?? null;
        }
        
        // 如果是共享对象，则取共享对象锁
        if ($this->shareEntity_sf651l) {
            return self::$eContainer_sf651l[$this->objectId_sf651l]['lock'] ?? null;
        }
        
        // 获取普通锁
        return $this->lockCh_sf651l ?? null;
    }    private function setLock_sf651l()
    {
        $lock = new \Swoole\Coroutine\Channel(1);
        // 批对象
        if ($this->isBatchObject_sf651l()) {
            self::$relTmpContainer_sf651l[$this->relId_sf651l][self::LOCK_KEY_SF876Y] = $lock;
            return $lock;
        }
        
        // 共享对象
        if ($this->shareEntity_sf651l) {
            self::$eContainer_sf651l[$this->objectId_sf651l]['lock'] = $lock;
            return $lock;
        }
        
        // 普通锁
        $this->lockCh_sf651l = $lock;
        return $lock;
    }    private function trytoBuild_sf651l(bool $isClone = false)
    {
        if ($this->trytoFetchFromLocal_sf651l($isClone)) {
            return;
        }
        
        // 加锁
        if (!$this->lock_sf651l() && $this->trytoFetchFromLocal_sf651l($isClone)) {
            // 加锁失败（会发生锁等待）需再次尝试从本地获取
            return;
        }
        
        try {
             $this->setRealObject_sf651l($this->buildRealObject_sf651l(), $isClone);           
        } finally {
            $this->unlock_sf651l();
        }
    }

    private function buildRealObject_sf651l()
    {
        if ($isBatch = $this->isBatchObject_sf651l()) {
            $params = array_merge([$this->relObjIds_sf651l], $this->batchExtra_sf651l ?? []);
        } else {
            $params = $this->extra_sf651l ?? [];
            if (self::IS_ENTITY_SF876Y) {
                array_unshift($params, $this->objectId_sf651l);
            }
        }
        
        $rst = call_user_func($isBatch ? self::$batchInitFunc2_sf651l : self::$initFunc_sf651l, ...$params);
        
        if (!$rst) {
            throw new \Exception('build object for ' . self::REAL_CLS_NAME_SF876Y . ' fail');
        }
        
        if (!$this->isBatchObject_sf651l()) {
            return $rst;
        }
        
        // 批对象，需设置到临时容器中
        $delObjs = self::$relTmpContainer_sf651l[$this->relId_sf651l][self::DEL_KEY_SF876Y] ?? [];
        $currObj = null;
        foreach ($rst as $obj) {
            if (!$obj instanceof Identifiable) {
                continue;
            }
            
            $id = $obj->id();
            if ($id == $this->objectId_sf651l) {
                $currObj = $obj;
            }
            
            if (!isset($delObjs[$id])) {
                self::$relTmpContainer_sf651l[$this->relId_sf651l][$id] = $obj;
            }
        }
        
        if (!$currObj) {
            throw new \Exception('build object for ' . self::REAL_CLS_NAME_SF876Y . ' failed');
        }
        
        return $currObj;
    }    private function trytoFetchFromLocal_sf651l(bool $isClone = false)
    {
        // 真实对象已经创建了
        if ($this->object_sf651l) {
            return true;
        }
        
        // 如果是共享对象，则先检查容器中有没有现成的
        if ($this->shareEntity_sf651l && isset(self::$eContainer_sf651l[$this->objectId_sf651l])) {
            $this->object_sf651l = self::$eContainer_sf651l[$this->objectId_sf651l]['obj'];
            // clone场景不能增加引用计数
            if (!$isClone) {
                self::$eContainer_sf651l[$this->objectId_sf651l]['cnt'] += 1;
            }
            return true;
        }
        
        // 如果是批对象，检查批对象容器中有没有
        if ($this->isBatchObject_sf651l() && isset(self::$relTmpContainer_sf651l[$this->relId_sf651l][$this->objectId_sf651l])) {
            $this->setRealObject_sf651l(self::$relTmpContainer_sf651l[$this->relId_sf651l][$this->objectId_sf651l], $isClone);
            return true;
        }
        
        return false;
    }    private function setRealObject_sf651l($object, bool $isClone = false) {
        if (!$object) {
            throw new \Exception('get object of ' . self::REAL_CLS_NAME_SF876Y . 'fail');
        }
        
        $this->object_sf651l = $object;
        
        // 如果共享对象，则放到容器中（注意：如果是clone场景，初始引用计数应设置为0，因为克隆对象会分离出去，不能占引用计数）
        if ($this->shareEntity_sf651l && !isset(self::$eContainer_sf651l[$this->objectId_sf651l])) {
            self::$eContainer_sf651l[$this->objectId_sf651l] = ['cnt' => $isClone ? 0 : 1, 'obj' => $object];
        }
    }    private function interceptor_sf651l(string $methodName, ...$args)
    {
        $this->trytoBuild_sf651l();
        return call_user_func([$this->object_sf651l, $methodName], ...$args);
    }

    public function __get($name)
    {
        // 先检查属性是不是protected/private的，如果是，而类没有定义__get，则不能访问
        // 必须先做此检查，因为代理类和真实类属于继承关系，代理类天然能访问真实类的protected属性，即使真实类没有__get方法
        if (!self::canMagicOp_sf651l($name, '__get')) {
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
        if (!self::canMagicOp_sf651l($name, '__set')) {
            throw new \Exception("can not set property $name on class " . self::REAL_CLS_NAME_SF876Y);
        }
        
        // 注意要在build后检查，因为PHP可以动态设置属性
        $this->trytoBuild_sf651l();
        if (!property_exists($this->object_sf651l, $name)) {
            throw new \Exception('class ' . self::REAL_CLS_NAME_SF876Y . ' has no property ' . $name);
        }
        $this->object_sf651l->{$name} = $value;
    }

    private static function canMagicOp_sf651l(string $propName, string $op): bool
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
        $arr = ['objectId_sf651l', 'shareEntity_sf651l', 'rebuild_sf651l', 'relId_sf651l'];
        if (!$this->rebuild_sf651l) {
            // 如果反序列化后不重新构建真实对象，则需要在序列化之前构建好，否则可能会导致未知错误
            $this->trytoBuild_sf651l();
            $arr[] = 'object_sf651l';
        } else {
            if ($this->isBatchObject_sf651l()) {
                $arr[] = 'batchExtra_sf651l';
                if (self::$batchInitFunc_sf651l && !self::$batchInitFunc_sf651l instanceof \Closure) {
                    // 将构造器保存起来，注意闭包无法序列化
                    $this->batchInitFunc2_sf651l = self::$batchInitFunc_sf651l;
                    $arr[] = 'batchInitFunc2_sf651l';
                }
            } else {
                $arr[] = 'extra_sf651l';
                if (self::$initFunc_sf651l && !self::$initFunc_sf651l instanceof \Closure) {
                    // 将构造器保存起来，注意闭包无法序列化
                    $this->initFunc2_sf651l = self::$initFunc_sf651l;
                    $arr[] = 'initFunc2_sf651l';
                }
            }
        }
        
        return $arr;
    }

    public function __wakeup()
    {
        $this->initProps_sf651l();
        
        if (!$this->rebuild_sf651l && $this->shareEntity_sf651l) {
            // 非重建且共享型实体对象，要处理共享对象情况
            $this->object_sf651l = $this->fetchShareEntity_sf651l($this->object_sf651l);
        }
        
        // 恢复构造器
        if (!self::$initFunc_sf651l && $this->initFunc2_sf651l) {
            self::$initFunc_sf651l = $this->initFunc2_sf651l;
            $this->initFunc2_sf651l = null;
        }
        
        if (!self::$batchInitFunc_sf651l && $this->batchInitFunc2_sf651l) {
            self::$batchInitFunc_sf651l = $this->batchInitFunc2_sf651l;
            $this->batchInitFunc2_sf651l = null;
        }
    }

    public function __clone()
    {
        if (!$this->object_sf651l) {
            $this->trytoBuild_sf651l(true);
            if ($this->shareEntity_sf651l || $this->isBatchObject_sf651l()) {
                $this->object_sf651l = clone $this->object_sf651l;
            }
        } else {
            $this->object_sf651l = clone $this->object_sf651l;
        }
        
        // 改成独占式
        $this->shareEntity_sf651l = false;
        // 改成非批对象
        $this->relId_sf651l = '';
        $this->relObjIds_sf651l = [];
        $this->lockCh_sf651l = null;
    }

    public function __isset($name)
    {
        if (!self::canMagicOp_sf651l($name, '__isset')) {
            throw new \Exception("can not access property $name on class " . self::REAL_CLS_NAME_SF876Y);
        }
        
        $this->trytoBuild_sf651l();
        return isset($this->object_sf651l->{$name});
    }

    public function __unset($name)
    {
        if (!self::canMagicOp_sf651l($name, '__unset')) {
            throw new \Exception("can not access property $name on class " . self::REAL_CLS_NAME_SF876Y);
        }
        
        $this->trytoBuild_sf651l();
        unset($this->object_sf651l->{$name});
    }

    private function fetchShareEntity_sf651l($entity = null, bool $replace = true)
    {
        if (!self::IS_ENTITY_SF876Y || !$this->shareEntity_sf651l) {
            return $entity;
        }
        
        $id = $this->objectId_sf651l;
        if ($entity) {
            if (!isset(self::$eContainer_sf651l[$id])) {
                // 容器中没有共享对象，写入
                self::$eContainer_sf651l[$id] = ['cnt' => 1, 'obj' => $entity];
                return $entity;
            } else {
                // 容器中有共享对象，则看是否要使用容器中的对象
                if ($replace) {
                    self::$eContainer_sf651l[$id]['cnt'] += 1;
                    return self::$eContainer_sf651l[$id]['obj'];
                } else {
                    return $entity;
                }
            }
        } else {
            // 没有提供$entity
            if (isset(self::$eContainer_sf651l[$id])) {
                self::$eContainer_sf651l[$id]['cnt'] += 1;
                return self::$eContainer_sf651l[$id]['obj'];
            } else {
                return null;
            }
        }
    }

    public static function createProxyPx771jdh7($object = null, array $extra = [], $initFunc = 'newInstance', bool $shareEntity = true, bool $rebuildAfterSleep = true, string $batchRelId = '', array $batchObjIds = [])
    {
        if ($object instanceof IWrap) {
            // 防止对代理对象做包装
            return $object;
        }
        
        // 创建代理对象
        $proxy = self::getReflectionCLass_sf651l()->newInstanceWithoutConstructor();
        $proxy->rebuild_sf651l = $rebuildAfterSleep;
        // 批对象id
        $proxy->relId_sf651l = $batchRelId;
        $proxy->relObjIds_sf651l = $batchObjIds;
        // 初始化属性
        $proxy->initProps_sf651l();
        // 设置真实对象/标识
        $proxy->setObject_sf651l($object, $shareEntity);
        // 设置初始化器
        if ($batchRelId) {
            $proxy->setBatchInitializer_sf651l($initFunc, $extra);
        } else {
            $proxy->setSingleInitializer_sf651l($initFunc, $extra);
        }
        
        return $proxy;
    }

    public static function createBatchProxyPx771jdh7(array $ids, $extra = [], $initFunc = 'newInstances', bool $shareEntity = true): array
    {
        if (!$ids) {
            return [];
        }
        
        // 生成relId
        sort($ids);
        $relId = md5(self::REAL_CLS_NAME_SF876Y . impolde(',', $ids) . mt_rand(100, 1000000));
        
        // 初始化批对象临时容器
        self::$relTmpContainer_sf651l[$relId] = [];
        
        // 创建代理对象
        $objs = [];
        foreach ($ids as $id) {
            $objs[] = self::createProxyPx771jdh7($id, $extra, $initFunc, $shareEntity, false, $relId, $ids);
        }
        
        return $objs;
    }    private function isBatchObject_sf651l(): bool
    {
        return boolval($this->relId_sf651l);
    }    private static function getReflectionCLass_sf651l()
    {
        static $r;
        $r = $r ?? new \ReflectionClass(__CLASS__);
        return $r;
    }}