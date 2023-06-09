<?php

namespace WecarSwoole\OTA;

use EasySwoole\Utility\Str;

/**
 * 对象转成数组
 * Trait ObjectToArray
 * @package WecarSwoole\OTA
 */
trait ObjectToArray
{
    /**
     * @param bool $camelToSnake 是否将驼峰风格属性名转换成下划线风格
     * @param bool $withNull 是否包含 null 属性
     * @param bool $zip 是否将多维压成一维，如
     *              ['age'=>12,'address'=>['city'=>'深圳','area'=>'福田']] 会变成 ['age'=>12,'city'=>'深圳','area'=>'福田']
     * @param array $exFields 需要排除的字段
     * @return array
     */
    public function toArray(
        bool $camelToSnake = true,
        bool $withNull = true,
        bool $zip = false,
        array $exFields = []
    ): array {
        $data = $this->getPropertiesValue($withNull, $exFields);

        if (!$camelToSnake && !$zip) {
            return $data;
        }

        // 多维压成一维
        if ($zip) {
            $data = self::zip($data);
        }

        // 驼峰转 snake
        if ($camelToSnake) {
            $data = self::camelToSnake($data);
        }

        // 后置钩子
        return $this->__afterToArray($data);
    }

    /**
     * 具体类可以重写该钩子以实现自定义逻辑
     * @param array $data
     * @return array
     */
    protected function __afterToArray(array $data): array
    {
        return $data;
    }

    protected function getPropertiesValue(bool $withNull, array $exFields = []): array
    {
        $values = get_object_vars($this);

        if (!$withNull) {
            $values = array_filter($values, function ($value) {
                return !is_null($value);
            });
        }

        foreach ($values as $propName => &$propValue) {
            if (in_array($propName, $exFields)) {
                unset($values[$propName]);
                continue;
            }

            if (is_bool($propValue)) {
                $propValue = intval($propValue);
            } elseif ($propValue instanceof IExtractable) {
                $propValue = $propValue->toArray(false, $withNull);
            }
        }

        return $values;
    }

    protected static function zip(array $data): array
    {
        $result = [];
        foreach ($data as $k => $val) {
            if (is_array($val)) {
                $result = array_merge($result, self::zip($val));
            } else {
                $result[$k] = $val;
            }
        }

        return $result;
    }

    protected static function camelToSnake(array $data): array
    {
        foreach ($data as $k => &$val) {
            if (is_string($k) && ctype_alpha($k) && !ctype_lower($k)) {
                $data[Str::snake($k)] = $val;
                unset($data[$k]);
            }

            if (is_array($val)) {
                $val = self::camelToSnake($val);
            }
        }

        return $data;
    }
}
