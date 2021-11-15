<?php

namespace WecarSwoole\LazyProxy;

/**
 * 代理类生成器
 * 注意：此处没有处理对静态属性和静态方法的访问，如果代码里面有非正规使用（比如通过$this访问静态成员或方法），会导致问题（$this指向的是代理对象）
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
     * @throws \ReflectionException
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

        $reflection = new \ReflectionClass($baseClassName);
        $class = "namespace $namespace;\n"
                 . "use WecarSwoole\LazyProxy\IWrap;\n\n"
                 . "final class $shortName extends " . $baseClassName . " implements IWrap {\n"
                 . "private const REAL_CLS_NAME_SF876Y = '$baseClassName';\n"
                 . "private const INNER_PROPS_SF876Y = " . self::fetchInnerProperties($reflection)
                 . "private const MAGIC_METHODS_SF876Y = " . self::fetchMagicMethods($reflection)
                 . "private const IS_ENTITY_SF876Y = " . ($reflection->implementsInterface(Identifiable::class) ? 'true' : 'false') . ";\n"
                 . "private static \$eContainer_sf651l = [];\n"// 实体对象容器，格式：[id => [wraper ref num, object]]，记录实体对象本身以及被wraper引用次数
                 . "private static \$initFunc_sf651l;\n"// 真实对象构造器。如果是构造实体对象，则参数是id+...$extra，普通对象是...$extra
                 . "private \$object_sf651l;\n"// 真实对象
                 . "private \$objectId_sf651l;\n"// 真实对象id（只有实体对象才有）
                 . "private \$shareEntity_sf651l;\n"// wraper是否共享对象
                 . "private \$rebuild_sf651l;\n"// 反序列化时是否要重建对象
                 . "private \$extra_sf651l;\n"// 构造真实对象时传入的额外参数
                 . self::createMethods($reflection)
                 . "}";

        // 测试
        file_put_contents(__DIR__."/tmp_class.php", "<?php\n" . $class);
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
                        // wraper尚未关联真实对象，或者真实对象属于独占型对象（非共享对象），不用处理
                        if (!\$this->object_sf651l || !\$this->shareEntity_sf651l) {
                            return;
                        }
                        
                        if (!isset(self::\$eContainer_sf651l[\$this->objectId_sf651l])) {
                            return;
                        }
                        
                        // 将容器中该对象的wraper引用计数减一
                        \$cnt = self::\$eContainer_sf651l[\$this->objectId_sf651l][0];
                        if (\$cnt <= 1) {
                            unset(self::\$eContainer_sf651l[\$this->objectId_sf651l]);
                        } else {
                            self::\$eContainer_sf651l[\$this->objectId_sf651l][0] -= 1;
                        }
                    }\n\n
                 SEG;

        // unset所有public的实例属性，让其能触发__set、__get
        $methods .= <<<SEG
                    private function initProps_sf651l()
                    {
                        \$props = self::getReflectionCLass()->getProperties(\ReflectionProperty::IS_PUBLIC);
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
                            if (
                                is_object(\$object)
                                && isset(self::\$eContainer_sf651l[\$object->id()])
                                && spl_object_hash(\$object) != spl_object_hash(self::\$eContainer_sf651l[\$object->id()][1])
                            ) {
                                // 传入的对象和容器的不是一个，则本wraper采用独占式
                                \$this->shareEntity_sf651l = false;
                            } else {
                                \$this->shareEntity_sf651l = \$shareEntity;
                            }
                        }
                    }\n\n
                SEG;

        // 设置初始化器（用于创建真实对象）
        // $initFunc可以是callable，也可以是方法名称（此时先认为它是基类的静态方法，如果不存在，则认为是独立函数，如果都不是则抛异常）
        // $initFunc的第一个参数是对象标识(id)，后面的参数是自定义的（通过...$extra透传）
        // extra建议仅传基本数据类型的值，不要传复杂类型（如对象）
        $methods .= <<<SEG
                    private function setInitializer_sf651l(\$initFunc, \$extra = [])
                    {
                        \$this->extra_sf651l = \$extra;
                        
                        // 构造器只需要设置一次
                        if (self::\$initFunc_sf651l) {
                            return;
                        }
                        
                        if (\$initFunc instanceof \Closure) {
                            self::\$initFunc_sf651l = \$initFunc;
                            return;
                        }
                        
                        if (is_callable(\$initFunc)) {
                            self::\$initFunc_sf651l = \Closure::fromCallable(\$initFunc);
                            return;
                        }
                        
                        if (is_callable(__CLASS__, \$initFunc)) {
                            self::\$initFunc_sf651l = \Closure::fromCallable([__CLASS__, \$initFunc]);
                            return;
                        }
                        
                        throw new \Exception("invalid init function:\$initFunc");
                    }\n\n
                SEG;

        // 构建真实对象
        $methods .= <<<SEG
                    private function trytoBuild_sf651l()
                    {
                        // 真实对象已经创建了
                        if (\$this->object_sf651l) {
                            return;
                        }
                        
                        // 创建真实对象
                        // 如果是共享对象，则先检查容器中有没有现成的
                        if (\$this->shareEntity_sf651l && isset(self::\$eContainer_sf651l[\$this->objectId_sf651l])) {
                            self::\$eContainer_sf651l[\$this->objectId_sf651l][0] += 1;
                            \$this->object_sf651l = self::\$eContainer_sf651l[\$this->objectId_sf651l][1];
                            return;
                        }
                        
                        // 构造
                        \$params = \$this->extra_sf651l ?? [];
                        if (self::IS_ENTITY_SF876Y) {
                            array_unshift(\$params, \$this->objectId_sf651l);
                        }
                        
                        \$this->object_sf651l = call_user_func(self::\$initFunc_sf651l, ...\$params);
                        if (!\$this->object_sf651l) {
                            throw new \Exception('create object of ' . self::REAL_CLS_NAME_SF876Y . 'fail');
                        }
                        
                        // 如果共享对象，则放到容器中
                        if (\$this->shareEntity_sf651l) {
                            self::\$eContainer_sf651l[\$this->objectId_sf651l] = [1, \$this->object_sf651l];
                        }
                    }\n\n
                SEG;

        // 方法拦截器，拦截对真实对象的public、protected方法调用
        $methods .= <<<SEG
                    private function interceptor_sf651l(string \$methodName, ...\$args)
                    {
                        \$this->trytoBuild_sf651l();
                        return call_user_func([\$this->object_sf651l, \$methodName], ...\$args);
                    }\n\n
                SEG;

        $methods .= <<<SEG
                    public function __get(\$name)
                    {
                        // 先检查属性是不是protected/private的，如果是，而类没有定义__get，则不能访问
                        // 必须先做此检查，因为代理类和真实类属于继承关系，代理类天然能访问真实类的protected属性，即使真实类没有__get方法
                        if (!self::canMagicOp(\$name, '__get')) {
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
                        if (!self::canMagicOp(\$name, '__set')) {
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
                    private static function canMagicOp(string \$propName, string \$op): bool
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
                        \$arr = ['objectId_sf651l', 'shareEntity_sf651l', 'rebuild_sf651l', 'extra_sf651l'];
                        if (!\$this->rebuild_sf651l) {
                            // 如果反序列化后不重新构建真实对象，则需要在序列化之前构建好，否则可能会导致未知错误
                            \$this->trytoBuild_sf651l();
                            \$arr[] = 'object_sf651l';
                        }
                        
                        return \$arr;
                    }\n\n
                SEG;

        $methods .= <<<SEG
                    public function __wakeup()
                    {
                        \$this->initProps_sf651l();
                        if (self::IS_ENTITY_SF876Y && !\$this->rebuild_sf651l && \$this->shareEntity_sf651l) {
                            // 非重建且共享型实体对象，要处理共享对象情况
                            \$this->object_sf651l = \$this->fetchShareEntity(\$this->object_sf651l);
                        }
                    }\n\n
                SEG;

        // 当clone wraper时，需要同时克隆真实对象，且将真实对象设置为独占型对象
        $methods .= <<<SEG
                    public function __clone()
                    {
                        // clone后需立即构建新clone的wraper里面的真实对象，否则在极端情况下可能会因所有的克隆对象都要去构建真实对象而导致严重的性能问题
                        \$this->trytoBuild_sf651l();
                        \$this->object_sf651l = clone \$this->object_sf651l;
                        \$this->shareEntity_sf651l = false;
                    }\n\n
                SEG;

        $methods .= <<<SEG
                    public function __isset(\$name)
                    {
                        if (!self::canMagicOp(\$name, '__isset')) {
                            throw new \Exception("can not access property \$name on class " . self::REAL_CLS_NAME_SF876Y);
                        }
                        
                        \$this->trytoBuild_sf651l();
                        return isset(\$this->object_sf651l->{\$name});
                    }\n\n
                SEG;

        $methods .= <<<SEG
                    public function __unset(\$name)
                    {
                        if (!self::canMagicOp(\$name, '__unset')) {
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
                    private function fetchShareEntity(\$entity = null, bool \$replace = true)
                    {
                        if (!self::IS_ENTITY_SF876Y || !\$this->shareEntity_sf651l) {
                            return \$entity;
                        }
                        
                        \$id = \$this->objectId_sf651l;
                        if (\$entity) {
                            if (!isset(self::\$eContainer_sf651l[\$id])) {
                                // 容器中没有共享对象，写入
                                self::\$eContainer_sf651l[\$id] = [1, \$entity];
                                return \$entity;
                            } else {
                                // 容器中有共享对象，则看是否要使用容器中的对象
                                if (\$replace) {
                                    self::\$eContainer_sf651l[\$id][0] += 1;
                                    return self::\$eContainer_sf651l[\$id][1];
                                } else {
                                    return \$entity;
                                }
                            }
                        } else {
                            // 没有提供\$entity
                            if (isset(self::\$eContainer_sf651l[\$id])) {
                                self::\$eContainer_sf651l[\$id][0] += 1;
                                return self::\$eContainer_sf651l[\$id][1];
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
        $methods .= <<<SEG
                    public static function createProxyPx771jdh7(\$object = null, array \$extra = [], \$initFunc = 'buildInstance', bool \$shareEntity = true, bool \$rebuildAfterSleep = true)
                    {
                        if (\$object instanceof IWrap) {
                            // 防止对代理对象做包装
                            return \$object;
                        }
                        
                        // 创建代理对象
                        \$proxy = self::getReflectionCLass()->newInstanceWithoutConstructor();
                        \$proxy->rebuild_sf651l = \$rebuildAfterSleep;
                        // 初始化属性
                        \$proxy->initProps_sf651l();
                        // 设置真实对象/标识
                        \$proxy->setObject_sf651l(\$object, \$shareEntity);
                        // 设置初始化器
                        \$proxy->setInitializer_sf651l(\$initFunc, \$extra);
                        
                        return \$proxy;
                    }\n\n
                SEG;

        $methods .= <<<SEG
                    private static function getReflectionCLass()
                    {
                        static \$r;
                        \$r = \$r ?? new \ReflectionClass(__CLASS__);
                        return \$r;
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
