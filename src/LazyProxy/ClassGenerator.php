<?php

namespace WecarSwoole\LazyProxy;

use WecarSwoole\Util\Reflection;

/**
 * 代理类生成器
 * 注意：此处没有处理对静态属性和静态方法的访问，如果代码里面有非正规使用（比如通过$this访问静态成员或方法），会导致问题（$this指向的是代理对象）
 * 注意：__get、__set、__unset、__isset等方法里面不支持协程切换（参见swoole文档），为避免出问题，尽量不要通过这些方法访问私有属性，防止多协程并发操作时出问题
 * Class ClassGenerator
 * @package WecarSwoole\LazyProxy
 */
class ClassGenerator
{
    private const PROXY_MASK = '\\_ZSLYYDS_\\';

    /**
     * 生成代理类
     * @param string $baseClassName 类全名
     * @return string
     * @throws \Exception
     */
    public static function generateProxyClass(string $baseClassName): string
    {
        $baseClassName = '\\' . ltrim($baseClassName, '\\');
        if (strpos($baseClassName, self::PROXY_MASK) !== false) {
            // 该类是代理类，不创建
            return $baseClassName;
        }

        $proxyClassName = self::getProxyClassName($baseClassName);
        if (class_exists($proxyClassName)) {
            return $proxyClassName;
        }

        $clsNameArr = array_filter(explode('\\', $proxyClassName));
        $shortName = array_pop($clsNameArr);
        $namespace = implode('\\', $clsNameArr);

        // 关于代理类的锁：分单一对象锁、共享对象锁和批对象锁：
        // 如果是批对象，则创建前要加批对象锁，使得同一批次的对象不会并发创建
        // 如果是共享对象，则创建前要加共享对象锁，使得相同id的共享对象不会并发创建
        // 否则加单一对象锁
        // 以上锁优先级递减：批对象锁>共享对象锁>单一对象锁
        // 批对象锁放在$relTmpContainer_sf651l中，共享对象锁放在$eContainer_sf651l中
        $reflection = Reflection::getReflectionClass($baseClassName);
        $class = "namespace $namespace;\n"
                 . "use WecarSwoole\LazyProxy\IWrap;\n\n"
                 . "use WecarSwoole\LazyProxy\Identifiable;\n\n"
                 . "use WecarSwoole\Util\Reflection\n\n"
                 . "final class $shortName extends " . $baseClassName . " implements IWrap {\n"
                 . "private const REAL_CLS_NAME_SF876Y = '$baseClassName';\n"
                 . "private const LOCK_KEY_SF876Y = '__lockkey6q901m19a8__';\n"// 批对象锁的key
                 . "private const DEL_KEY_SF876Y = '__delkey6q901m19a8__';\n"// 批对象已删除key
                 . "private const INNER_PROPS_SF876Y = " . self::fetchInnerProperties($reflection)
                 . "private const MAGIC_METHODS_SF876Y = " . self::fetchMagicMethods($reflection)
                 . "private const IS_ENTITY_SF876Y = " . ($reflection->implementsInterface(Identifiable::class) ? 'true' : 'false') . ";\n"
                 . "private static \$eContainer_sf651l = [];\n"// 实体共享对象容器，格式：[id => ['cnt' => wraper ref num, 'obj' => object, 'lock' => $lock]]，记录实体对象本身以及被wraper引用次数
                 . "private static \$relTmpContainer_sf651l = [];\n"// 批对象临时容器，格式：[relid => [id => object]]
                 . "private static \$initFunc_sf651l;\n"// 真实对象构造器。如果是构造实体对象，则参数是id+...$extra，普通对象是...$extra
                 . "private static \$batchInitFunc_sf651l;\n"// 真实批对象构造器，参数格式：function(array $ids, ...$extra): Identifiable[]
                 . "private \$initFunc2_sf651l;\n"// 在对象中暂存构造器
                 . "private \$batchInitFunc2_sf651l;\n"// 在对象中暂存批构造器
                 . "private \$object_sf651l;\n"// 真实对象
                 . "private \$objectId_sf651l;\n"// 真实对象id（只有实体对象才有）
                 . "private \$relId_sf651l;\n"// 批对象的relid（关联id）。同一批对象的relid相同，关联到$relTmpContainer_sf651l
                 . "private \$relObjIds_sf651l;\n"// 批对象id列表
                 . "private \$shareEntity_sf651l;\n"// wraper是否共享对象
                 . "private \$rebuild_sf651l;\n"// 反序列化时是否要重建对象
                 . "private \$extra_sf651l;\n"// 构造真实对象时传入的额外参数
                 . "private \$batchExtra_sf651l;\n"
                 . "private \$lockCh_sf651l;\n"// 单一对象锁，Channel，防止多协程同时构建对象
                 . self::createMethods($reflection)
                 . "}";

        eval($class);
        return $proxyClassName;
    }

    private static function fetchMagicMethods(\ReflectionClass $class): string
    {
        $rtn = '[';
        $methods = $class->getMethods(\ReflectionMethod::IS_PUBLIC);
        foreach ($methods as $method) {
            if ($method->isStatic() || strpos($method->getName(), '__') !== 0) {
                continue;
            }
            $rtn .= '"'.$method->getName().'" => true,';
        }
        $rtn .= "];\n";
        return $rtn;
    }

    private static function fetchInnerProperties(\ReflectionClass $class): string
    {
        $props = $class->getProperties(\ReflectionProperty::IS_PROTECTED | \ReflectionProperty::IS_PRIVATE);
        $rtn = '[';
        foreach ($props as $property) {
            if ($property->isStatic()) {
                continue;
            }

            $rtn .= '"'.$property->getName().'" => true,';
        }
        $rtn .= "];\n";
        return $rtn;
    }

    private static function getProxyClassName(string $baseClassName)
    {
        return '\Wecarswoole\LazyLoad\Proxy' . self::PROXY_MASK . trim($baseClassName, '\\') . '\\TmpProxydu736';
    }

    /**
     * @param \ReflectionClass $reflection
     * @return string
     * @throws \ReflectionException
     */
    private static function createMethods(\ReflectionClass $reflection): string
    {
        $methods = "\n";
        $publicMethods = $reflection->getMethods(\ReflectionMethod::IS_PUBLIC);

        // 覆盖基类public的实例方法
        foreach ($publicMethods as $method) {
            if (
                $method->isConstructor() ||
                $method->isDestructor() ||
                $method->isStatic() ||
                in_array($method->getName(), ['__get', '__set', '__sleep', '__wakeup', '__clone', '__unset', '__isset'])
            ) {
                continue;
            }

            $methods .= 'public function ' . $method->getName() . '(';
            $methods .= self::createMethodArgs($method) . ')' . self::createMethodReturnType($method) . "{\n";
            $methods .= self::createMethodBody($method) . "\n}\n\n";
        }

        // 析构函数，处理实体wraper引用情况
        $methods .= <<<SEG
                    public function __destruct()
                    {
                        \$this->clearBatchObject_sf651l();
                        \$this->clearShareObject_sf651l();
                    }\n\n
                 SEG;

        $methods .= <<<SEG
                    private function clearShareObject_sf651l()
                    {
                        if (!\$this->shareEntity_sf651l || !isset(self::\$eContainer_sf651l[\$this->objectId_sf651l])) {
                            return;
                        }
                        
                        // 共享对象容器是空的，销毁
                        if (empty(self::\$eContainer_sf651l[\$this->objectId_sf651l])) {
                            unset(self::\$eContainer_sf651l[\$this->objectId_sf651l]);
                            return;
                        }
                        
                        // 不存在cnt字段（但不为空，此时有lock字段，说明其他协程的wraper正在build对象，不能销毁）
                        if (!isset(self::\$eContainer_sf651l[\$this->objectId_sf651l]['cnt'])) {
                            return;
                        }
                        
                        // 将共享对象容器中该对象的wraper引用计数减一
                        \$cnt = self::\$eContainer_sf651l[\$this->objectId_sf651l]['cnt'];
                        if (\$cnt <= 1) {
                            unset(self::\$eContainer_sf651l[\$this->objectId_sf651l]);
                        } else {
                            self::\$eContainer_sf651l[\$this->objectId_sf651l]['cnt'] -= 1;
                        }
                    }\n\n
                SEG;

        $methods .= <<<SEG
                    private function clearBatchObject_sf651l()
                    {
                        if (!\$this->isBatchObject_sf651l() || !isset(self::\$relTmpContainer_sf651l[\$this->relId_sf651l])) {
                            return;
                        }
                        
                        \$c = self::\$relTmpContainer_sf651l[\$this->relId_sf651l];
                        
                        // 将本对象id放入删除列表中
                        if (!isset(\$c[self::DEL_KEY_SF876Y])) {
                            \$c[self::DEL_KEY_SF876Y] = [];
                        }
                        \$c[self::DEL_KEY_SF876Y][\$this->objectId_sf651l] = 1;
                        
                        // 如果所有对象都全部销毁了，则销毁容器
                        if (count(\$c[self::DEL_KEY_SF876Y]) == count(\$this->relObjIds_sf651l)) {
                            unset(self::\$relTmpContainer_sf651l[\$this->relId_sf651l]);
                            return;
                        }
                        
                        // 如果临时容器中存在本wraper的真实对象，则销毁
                        if (isset(\$c[\$this->objectId_sf651l])) {
                            unset(\$c[\$this->objectId_sf651l]);
                        }
                        
                        self::\$relTmpContainer_sf651l[\$this->relId_sf651l] = \$c;
                    }\n\n
                SEG;

        // 注意该方法是设置但对象代理模式的构造器
        $methods .= <<<SEG
                    public static function setInitializerForProxy763jhfq(string \$initFunc)
                    {
                        if (self::\$initFunc_sf651l || !is_callable(\$initFunc)) {
                            return;
                        }
                        
                        self::\$initFunc_sf651l = \$initFunc;
                    }\n\n
                SEG;

        // unset所有public的实例属性，让其能触发__set、__get
        $methods .= <<<SEG
                    private function initProps_sf651l()
                    {
                        \$props = Reflection::getReflectionClass(__CLASS__)->getProperties(\ReflectionProperty::IS_PUBLIC);
                        foreach (\$props as \$property) {
                            if (\$property->isStatic()) {
                                continue;
                            }
                            unset(\$this->{\$property->getName()});
                        }
                    }\n\n
                 SEG;

        // 设置object（真实对象）。可以传入对象（实体对象或者普通对象），也可以传入对象标识id
        // 传入对象id时，说明该对象需要做延迟加载
        $methods .= <<<SEG
                    private function setObject_sf651l(\$object = null, \$shareEntity = true)
                    {
                        if (is_object(\$object)) {
                            // 对象
                            \$this->object_sf651l = \$object;
                            \$this->objectId_sf651l = self::IS_ENTITY_SF876Y ? \$object->id() : '';
                        } elseif (is_int(\$object) || is_string(\$object) && \$object) {
                            // 实体标识
                            if (!self::IS_ENTITY_SF876Y) {
                                throw new \Exception('create proxy fail:' . self::REAL_CLS_NAME_SF876Y . ' is not entity');
                            }
                            \$this->object_sf651l = null;
                            \$this->objectId_sf651l = \$object; 
                        } else {
                            // 啥也不传，表示延迟创建普通对象
                            if (self::IS_ENTITY_SF876Y) {
                                // 实体必须传id
                                throw new \Exception('create proxy fail:entity id required');
                            }
                            \$this->object_sf651l = null;
                            \$this->objectId_sf651l = '';
                        }
                        
                        if (!self::IS_ENTITY_SF876Y) {
                            // 普通对象都采用独占式
                            \$this->shareEntity_sf651l = false;
                        } else {
                            if (is_object(\$object)) {
                                if (
                                    isset(self::\$eContainer_sf651l[\$object->id()])
                                    && isset(self::\$eContainer_sf651l[\$object->id()]['obj'])
                                    && spl_object_hash(\$object) != spl_object_hash(self::\$eContainer_sf651l[\$object->id()]['obj'])
                                ) {                                    
                                    // 传入的对象和共享对象容器中的不是一个，则本wraper采用独占式
                                    \$this->shareEntity_sf651l = false;
                                } else {
                                    if ((!isset(self::\$eContainer_sf651l[\$object->id()]) || !isset(self::\$eContainer_sf651l[\$object->id()]['obj'])) && \$shareEntity) {
                                        // 共享该对象
                                        self::\$eContainer_sf651l[\$object->id()] = array_merge(self::\$eContainer_sf651l[\$object->id()] ?? [], ['cnt' => 1, 'obj' => \$object]);
                                    }
                                    \$this->shareEntity_sf651l = \$shareEntity;
                                }
                            } else {
                                \$this->shareEntity_sf651l = \$shareEntity;
                            }
                        }
                        
                        // 如果是共享对象，则要初始化共享对象容器
                        if (\$this->shareEntity_sf651l && !isset(self::\$eContainer_sf651l[\$this->objectId_sf651l])) {
                            self::\$eContainer_sf651l[\$this->objectId_sf651l] = [];
                        }
                    }\n\n
                SEG;

        // 设置初始化器（用于创建真实对象）
        // $initFunc可以是callable，也可以是方法名称（此时先认为它是基类的静态方法，如果不存在，则认为是独立函数，如果都不是则抛异常）
        // $initFunc的第一个参数是对象标识(id)，后面的参数是自定义的（通过...$extra透传）
        // extra建议仅传基本数据类型的值，不要传复杂类型（如对象）
        $methods .= <<<SEG
                    private function setSingleInitializer_sf651l(\$initFunc, \$extra = [])
                    {
                        \$this->extra_sf651l = \$extra;
                        
                        // 构造器只需要设置一次
                        if (self::\$initFunc_sf651l) {
                            return;
                        }
                        
                        if (is_callable(\$initFunc)) {
                            self::\$initFunc_sf651l = \$initFunc;
                            return;
                        }

                        if (is_callable(__CLASS__, \$initFunc)) {
                            self::\$initFunc_sf651l = [__CLASS__, \$initFunc];
                            return;
                        }
                        
                        throw new \Exception("invalid init function:\$initFunc");
                    }\n\n
                SEG;

        $methods .= <<<SEG
                    private function setBatchInitializer_sf651l(\$initFunc, \$extra = [])
                    {
                        \$this->batchExtra_sf651l = \$extra;
                        // 构造器只需要设置一次
                        if (self::\$batchInitFunc_sf651l) {
                            return;
                        }
                        
                        if (is_callable(\$initFunc)) {
                            self::\$batchInitFunc_sf651l = \$initFunc;
                            return;
                        }

                        if (method_exists(__CLASS__, \$initFunc)) {
                            self::\$batchInitFunc_sf651l = [__CLASS__, \$initFunc];
                            return;
                        }
                        
                        throw new \Exception("invalid init function:\$initFunc");
                    }\n\n
                SEG;


        // 创建协程锁（只有在协程环境才创建）
        // 创建代理对象（或者反序列化后）需重建锁
        $methods .= <<<SEG
                    private function tryToCreateLock_sf651l()
                    {
                        if (\Swoole\Coroutine::getCid() == -1) {
                            return null;
                        }
                        
                        if (\$lock = \$this->getLock_sf651l()) {
                            return \$lock;
                        }
                        
                        return \$this->setLock_sf651l();
                    }\n\n
                SEG;

        // 上锁。如有其他协程已经上锁则会阻塞
        // 加锁成功返回true，否则返回false
        $methods .= <<<SEG
                    private function lock_sf651l()
                    {
                        if (!\$lock = \$this->tryToCreateLock_sf651l()) {
                            return true;
                        }
                        
                        // 这里无需区分到底是因为通道关闭了、还是超时了、还是通道空了返回的
                        return \$lock->push(1, 5);
                    }\n\n
                SEG;

        // 解锁，唤醒所有等待者
        $methods .= <<<SEG
                    private function unlock_sf651l()
                    {
                        if (!\$lock = \$this->getLock_sf651l()) {
                            return;
                        }
                        
                        // 关闭通道，唤醒所有等待者
                        \$lock->close();
                    }\n\n
                SEG;

        $methods .= <<<SEG
                    private function getLock_sf651l()
                    {
                        // 如果是批对象，则是批对象锁
                        if (\$this->isBatchObject_sf651l()) {
                            return self::\$relTmpContainer_sf651l[\$this->relId_sf651l][self::LOCK_KEY_SF876Y] ?? null;
                        }
                        
                        // 如果是共享对象，则取共享对象锁
                        if (\$this->shareEntity_sf651l) {
                            return self::\$eContainer_sf651l[\$this->objectId_sf651l]['lock'] ?? null;
                        }
                        
                        // 获取普通锁
                        return \$this->lockCh_sf651l ?? null;
                    }
                SEG;

        $methods .= <<<SEG
                    private function setLock_sf651l()
                    {
                        \$lock = new \Swoole\Coroutine\Channel(1);
                        // 批对象
                        if (\$this->isBatchObject_sf651l()) {
                            self::\$relTmpContainer_sf651l[\$this->relId_sf651l][self::LOCK_KEY_SF876Y] = \$lock;
                            return \$lock;
                        }
                        
                        // 共享对象
                        if (\$this->shareEntity_sf651l) {
                            self::\$eContainer_sf651l[\$this->objectId_sf651l]['lock'] = \$lock;
                            return \$lock;
                        }
                        
                        // 普通锁
                        \$this->lockCh_sf651l = \$lock;
                        return \$lock;
                    }
                SEG;

        // 构建真实对象
        // 需注意多协程下防止并发构建
        // $isClone bool 是否clone的场景，clone场景下：当从共享容器中取对象时，不将对象引用计数+1；创建对象放入共享容器时，初始引用计数设置为0
        $methods .= <<<SEG
                    private function trytoBuild_sf651l(bool \$isClone = false)
                    {
                        if (\$this->trytoFetchFromLocal_sf651l(\$isClone)) {
                            return;
                        }
                        
                        // 加锁
                        if (!\$this->lock_sf651l() && \$this->trytoFetchFromLocal_sf651l(\$isClone)) {
                            // 加锁失败（会发生锁等待）需再次尝试从本地获取
                            return;
                        }
                        
                        try {
                             \$this->setRealObject_sf651l(\$this->buildRealObject_sf651l(), \$isClone);           
                        } finally {
                            \$this->unlock_sf651l();
                        }
                    }\n\n
                SEG;

        $methods .= <<<SEG
                    private function buildRealObject_sf651l()
                    {
                        if (\$isBatch = \$this->isBatchObject_sf651l()) {
                            \$params = array_merge([\$this->relObjIds_sf651l], \$this->batchExtra_sf651l ?? []);
                        } else {
                            \$params = \$this->extra_sf651l ?? [];
                            if (self::IS_ENTITY_SF876Y) {
                                array_unshift(\$params, \$this->objectId_sf651l);
                            }
                        }
                        
                        \$rst = call_user_func(\$isBatch ? self::\$batchInitFunc_sf651l : self::\$initFunc_sf651l, ...\$params);
                        
                        if (!\$rst) {
                            throw new \Exception('build object for ' . self::REAL_CLS_NAME_SF876Y . ' fail');
                        }
                        
                        if (!\$this->isBatchObject_sf651l()) {
                            return \$rst;
                        }
                        
                        // 批对象，需设置到临时容器中
                        \$delObjs = self::\$relTmpContainer_sf651l[\$this->relId_sf651l][self::DEL_KEY_SF876Y] ?? [];
                        \$currObj = null;
                        foreach (\$rst as \$obj) {
                            if (!\$obj instanceof Identifiable) {
                                continue;
                            }
                            
                            \$id = \$obj->id();
                            if (\$id == \$this->objectId_sf651l) {
                                \$currObj = \$obj;
                            }
                            
                            if (!isset(\$delObjs[\$id])) {
                                self::\$relTmpContainer_sf651l[\$this->relId_sf651l][\$id] = \$obj;
                            }
                        }
                        
                        if (!\$currObj) {
                            throw new \Exception('build object for ' . self::REAL_CLS_NAME_SF876Y . ' failed');
                        }
                        
                        return \$currObj;
                    }
                SEG;

        $methods .= <<<SEG
                    private function trytoFetchFromLocal_sf651l(bool \$isClone = false)
                    {
                        // 真实对象已经创建了
                        if (\$this->object_sf651l) {
                            return true;
                        }
                        
                        // 如果是共享对象，则先检查容器中有没有现成的
                        if (\$this->shareEntity_sf651l && isset(self::\$eContainer_sf651l[\$this->objectId_sf651l]) && isset(self::\$eContainer_sf651l[\$this->objectId_sf651l]['obj'])) {
                            \$this->object_sf651l = self::\$eContainer_sf651l[\$this->objectId_sf651l]['obj'];
                            // clone场景不能增加引用计数
                            if (!\$isClone) {
                                self::\$eContainer_sf651l[\$this->objectId_sf651l]['cnt'] += 1;
                            }
                            return true;
                        }
                        
                        // 如果是批对象，检查批对象容器中有没有
                        if (\$this->isBatchObject_sf651l() && isset(self::\$relTmpContainer_sf651l[\$this->relId_sf651l][\$this->objectId_sf651l])) {
                            \$this->setRealObject_sf651l(self::\$relTmpContainer_sf651l[\$this->relId_sf651l][\$this->objectId_sf651l], \$isClone);
                            return true;
                        }
                        
                        return false;
                    }
                SEG;

        $methods .= <<<SEG
                    private function setRealObject_sf651l(\$object, bool \$isClone = false) {
                        if (!\$object) {
                            throw new \Exception('get object of ' . self::REAL_CLS_NAME_SF876Y . 'fail');
                        }
                        
                        \$this->object_sf651l = \$object;
                        
                        // 如果共享对象，则放到容器中（注意：如果是clone场景，初始引用计数应设置为0，因为克隆对象会分离出去，不能占引用计数）
                        if (\$this->shareEntity_sf651l && !isset(self::\$eContainer_sf651l[\$this->objectId_sf651l]['obj'])) {
                            self::\$eContainer_sf651l[\$this->objectId_sf651l] = array_merge(self::\$eContainer_sf651l[\$this->objectId_sf651l] ?? [], ['cnt' => \$isClone ? 0 : 1, 'obj' => \$object]);
                        }
                    }
                SEG;


        // 方法拦截器，拦截对真实对象的public、protected方法调用
        $methods .= <<<SEG
                    private function interceptor_sf651l(string \$methodName, ...\$args)
                    {
                        if (\$methodName == 'id' && self::IS_ENTITY_SF876Y) {
                            // 调实体的id()方法不会触发构建
                            return \$this->objectId_sf651l;
                        }
                    
                        \$this->trytoBuild_sf651l();
                        return call_user_func([\$this->object_sf651l, \$methodName], ...\$args);
                    }\n\n
                SEG;

        $methods .= <<<SEG
                    public function __get(\$name)
                    {
                        // 先检查属性是不是protected/private的，如果是，而类没有定义__get，则不能访问
                        // 必须先做此检查，因为代理类和真实类属于继承关系，代理类天然能访问真实类的protected属性，即使真实类没有__get方法
                        if (!self::canMagicOp_sf651l(\$name, '__get')) {
                            throw new \Exception("property \$name of class " . self::REAL_CLS_NAME_SF876Y . ' can not access');
                        }
                        
                        // 注意要在build后检查，因为PHP可以动态设置属性
                        \$this->trytoBuild_sf651l();
                        if (!property_exists(\$this->object_sf651l, \$name)) {
                            throw new \Exception('class ' . self::REAL_CLS_NAME_SF876Y . ' has no property ' . \$name);
                        }
                        return \$this->object_sf651l->{\$name};
                    }\n\n
                SEG;

        $methods .= <<<SEG
                    public function __set(\$name, \$value)
                    {
                        if (!self::canMagicOp_sf651l(\$name, '__set')) {
                            throw new \Exception("can not set property \$name on class " . self::REAL_CLS_NAME_SF876Y);
                        }
                        
                        // 注意要在build后检查，因为PHP可以动态设置属性
                        \$this->trytoBuild_sf651l();
                        if (!property_exists(\$this->object_sf651l, \$name)) {
                            throw new \Exception('class ' . self::REAL_CLS_NAME_SF876Y . ' has no property ' . \$name);
                        }
                        \$this->object_sf651l->{\$name} = \$value;
                    }\n\n
                SEG;

        $methods .= <<<SEG
                    private static function canMagicOp_sf651l(string \$propName, string \$op): bool
                    {
                        if (!isset(self::INNER_PROPS_SF876Y[\$propName])) {
                            // 不是私有属性，返回true
                            return true;
                        }
                        
                        if (isset(self::MAGIC_METHODS_SF876Y[\$op])) {
                            // 有魔术操作方法
                            return true;
                        }
                        
                        return false;
                    }\n\n
                SEG;


        $methods .= <<<SEG
                    public function __sleep()
                    {
                        \$arr = ['objectId_sf651l', 'shareEntity_sf651l', 'rebuild_sf651l', 'relId_sf651l'];
                        if (!\$this->rebuild_sf651l) {
                            // 如果反序列化后不重新构建真实对象，则需要在序列化之前构建好，否则可能会导致未知错误
                            \$this->trytoBuild_sf651l();
                            \$arr[] = 'object_sf651l';
                        } else {
                            // 这里只需考虑单一对象模式，批对象不支持反序列化后重建
                            \$arr[] = 'extra_sf651l';
                            if (self::\$initFunc_sf651l && !self::\$initFunc_sf651l instanceof \Closure) {
                                // 将构造器保存起来，注意闭包无法序列化
                                \$this->initFunc2_sf651l = self::\$initFunc_sf651l;
                                \$arr[] = 'initFunc2_sf651l';
                            }
                        }
                        
                        return \$arr;
                    }\n\n
                SEG;

        $methods .= <<<SEG
                    public function __wakeup()
                    {
                        \$this->initProps_sf651l();
                        
                        // 反序列化后要将属性改成非批对象，否则可能会影响其他批对象
                        \$this->relId_sf651l = '';
                        \$this->relObjIds_sf651l = [];
                        
                        if (!\$this->rebuild_sf651l && \$this->shareEntity_sf651l) {
                            // 非重建且共享型实体对象，要处理共享对象情况
                            \$this->object_sf651l = \$this->fetchShareEntity_sf651l(\$this->object_sf651l);
                        }
                        
                        // 恢复构造器
                        if (!self::\$initFunc_sf651l && \$this->initFunc2_sf651l) {
                            self::\$initFunc_sf651l = \$this->initFunc2_sf651l;
                            \$this->initFunc2_sf651l = null;
                        }
                    }\n\n
                SEG;

        // 当clone wraper时，需要同时克隆真实对象，且将真实对象设置为独占型对象
        $methods .= <<<SEG
                    public function __clone()
                    {
                        if (!\$this->object_sf651l) {
                            \$this->trytoBuild_sf651l(true);
                            if (\$this->shareEntity_sf651l || \$this->isBatchObject_sf651l()) {
                                \$this->object_sf651l = clone \$this->object_sf651l;
                            }
                        } else {
                            \$this->object_sf651l = clone \$this->object_sf651l;
                        }
                        
                        // 改成独占式
                        \$this->shareEntity_sf651l = false;
                        // 改成非批对象
                        \$this->relId_sf651l = '';
                        \$this->relObjIds_sf651l = [];
                        \$this->lockCh_sf651l = null;
                    }\n\n
                SEG;

        $methods .= <<<SEG
                    public function __isset(\$name)
                    {
                        if (!self::canMagicOp_sf651l(\$name, '__isset')) {
                            throw new \Exception("can not access property \$name on class " . self::REAL_CLS_NAME_SF876Y);
                        }
                        
                        \$this->trytoBuild_sf651l();
                        return isset(\$this->object_sf651l->{\$name});
                    }\n\n
                SEG;

        $methods .= <<<SEG
                    public function __unset(\$name)
                    {
                        if (!self::canMagicOp_sf651l(\$name, '__unset')) {
                            throw new \Exception("can not access property \$name on class " . self::REAL_CLS_NAME_SF876Y);
                        }
                        
                        \$this->trytoBuild_sf651l();
                        unset(\$this->object_sf651l->{\$name});
                    }\n\n
                SEG;

        // 从共享对象容器中获取实体对象
        // $entity Identifiable|null 如果提供了，当容器中没有对应实体时，则将该实体对象作为共享对象注册到容器中
        // $replace bool 提供了$entity而且容器中已经存在注册对象时，是否将容器中的对象返回（外面则可以使用容器中的对象替换掉$entity），false表示直接返回$entity
        // @return Identifiable|null 如果容器中（或者提供了$entity）则返回实体，两者都没有则返回null
        $methods .= <<<SEG
                    private function fetchShareEntity_sf651l(\$entity = null, bool \$replace = true)
                    {
                        if (!self::IS_ENTITY_SF876Y || !\$this->shareEntity_sf651l) {
                            return \$entity;
                        }
                        
                        \$id = \$this->objectId_sf651l;
                        if (\$entity) {
                            if (!isset(self::\$eContainer_sf651l[\$id]) || !isset(self::\$eContainer_sf651l[\$id]['obj'])) {
                                // 容器中没有共享对象，写入
                                self::\$eContainer_sf651l[\$id] = array_merge(self::\$eContainer_sf651l[\$id] ?? [], ['cnt' => 1, 'obj' => \$entity]);
                                return \$entity;
                            } else {
                                // 容器中有共享对象，则看是否要使用容器中的对象
                                if (\$replace) {
                                    self::\$eContainer_sf651l[\$id]['cnt'] += 1;
                                    return self::\$eContainer_sf651l[\$id]['obj'];
                                } else {
                                    return \$entity;
                                }
                            }
                        } else {
                            // 没有提供\$entity
                            if (isset(self::\$eContainer_sf651l[\$id]) && isset(self::\$eContainer_sf651l[\$id]['obj'])) {
                                self::\$eContainer_sf651l[\$id]['cnt'] += 1;
                                return self::\$eContainer_sf651l[\$id]['obj'];
                            } else {
                                return null;
                            }
                        }
                    }\n\n
                SEG;

        // 入口方法：创建代理实例
        // $object object/string/int 真实对象，或者对象的id
        // $extra array 当构建真实对象时传入的额外参数
        // $initFunc callable/string 构建真实对象的函数
        // $shareEntity bool 当对象是Identifiable时，是否采用共享对象的方式构造真实对象（当true时，同一个id在整个进程只会有一个对象，除非对wraper使用clone
        //                   注意：如果$object传入的是实体对象，而此时容器中已经存在另一个对象，而且两者不是同一个对象（hash值不同），则此时$shareEntity会强制为false
        // $rebuildAfterSleep bool 序列化后反序列化时是否要重建对象，如果要重建，则不会序列化真实对象（反序列化后重建），否则会序列化真实对象
        // $batchRelId string 批对象的relid
        $methods .= <<<SEG
                    public static function createProxyPx771jdh7(\$object = null, array \$extra = [], \$initFunc = 'newInstance', bool \$shareEntity = true, bool \$rebuildAfterSleep = true, string \$batchRelId = '', array \$batchObjIds = [])
                    {
                        if (\$object instanceof IWrap) {
                            // 防止对代理对象做包装
                            return \$object;
                        }
                        
                        // 创建代理对象
                        \$proxy = Reflection::getReflectionClass(__CLASS__)->newInstanceWithoutConstructor();
                        \$proxy->rebuild_sf651l = \$rebuildAfterSleep;
                        // 批对象id
                        \$proxy->relId_sf651l = \$batchRelId;
                        \$proxy->relObjIds_sf651l = \$batchObjIds;
                        // 初始化属性
                        \$proxy->initProps_sf651l();
                        // 设置真实对象/标识
                        \$proxy->setObject_sf651l(\$object, \$shareEntity);
                        // 设置初始化器
                        if (\$batchRelId) {
                            \$proxy->setBatchInitializer_sf651l(\$initFunc, \$extra);
                        } else {
                            \$proxy->setSingleInitializer_sf651l(\$initFunc, \$extra);
                        }
                        
                        return \$proxy;
                    }\n\n
                SEG;

        // 入口方法：创建批代理对象
        // 由于批对象的序列化场景比较复杂（可能是整批一起序列化，也可能是某一个或几个元素单独序列化），此处限制批代理模式下不支持反序列化后对象重建
        $methods .= <<<SEG
                    public static function createBatchProxyPx771jdh7(array \$ids, \$extra = [], \$initFunc = 'newInstances', bool \$shareEntity = true): array
                    {
                        if (!\$ids) {
                            return [];
                        }
                        
                        // 生成relId
                        sort(\$ids);
                        \$relId = md5(self::REAL_CLS_NAME_SF876Y . implode(',', \$ids) . mt_rand(100, 1000000));
                        
                        // 初始化批对象临时容器
                        self::\$relTmpContainer_sf651l[\$relId] = [];
                        
                        // 创建代理对象
                        \$objs = [];
                        foreach (\$ids as \$id) {
                            \$objs[] = self::createProxyPx771jdh7(\$id, \$extra, \$initFunc, \$shareEntity, false, \$relId, \$ids);
                        }
                        
                        return \$objs;
                    }
                SEG;

        $methods .= <<<SEG
                    private function isBatchObject_sf651l(): bool
                    {
                        return boolval(\$this->relId_sf651l);
                    }
                SEG;

        return $methods;
    }

    private static function createMethodReturnType(\ReflectionMethod $method): string
    {
        if (!$method->hasReturnType()) {
            return '';
        }

        $type = $method->getReturnType();
        return ': ' . ($type->allowsNull() ? '?' : '') . ($type->isBuiltin() ? '' : '\\') . $type->getName();
    }

    /**
     * @param \ReflectionMethod $method
     * @return string
     * @throws \ReflectionException
     */
    private static function createMethodArgs(\ReflectionMethod $method): string
    {
        $args = '';
        foreach ($method->getParameters() as $parameter) {
            if (version_compare(PHP_VERSION, '7.1', '>=')) {
                $args .= $parameter->allowsNull() && $parameter->getType() ? '?' : '';
            }

            if ($parameter->getType()) {
                $args .= $parameter->getType()->getName();
            }

            $args .= ' ' . ($parameter->isPassedByReference() ? '&' : '');
            $args .= self::isVariadic($parameter) ? '...' : '';

            $args .= '$' . $parameter->getName();

            if (self::hasDefaultValue($parameter)) {
                $args .= ' = ' . var_export(self::getDefaultValue($parameter), true);
            }

            $args .= ', ';
        }

        return rtrim($args, ', ');
    }

    private static function createMethodBody(\ReflectionMethod $method): string
    {
        return 'return $this->interceptor_sf651l("' . $method->getName() . '", ...func_get_args());';
    }

    private static function hasDefaultValue(\ReflectionParameter $parameter)
    {
        if (self::isVariadic($parameter)) {
            return false;
        }

        if ($parameter->isDefaultValueAvailable()) {
            return true;
        }

        return $parameter->isOptional();
    }

    private static function isVariadic(\ReflectionParameter $parameter): bool
    {
        return PHP_VERSION_ID >= 50600 && $parameter->isVariadic();
    }

    /**
     * @param \ReflectionParameter $parameter
     * @return mixed|null
     * @throws \ReflectionException
     */
    private static function getDefaultValue(\ReflectionParameter $parameter)
    {
        if (!$parameter->isDefaultValueAvailable()) {
            return null;
        }

        return $parameter->getDefaultValue();
    }
}
