<?php

namespace WecarSwoole\Util;

/**
 * 注解分析器
 * Class AnnotationAnalyser
 * @package WecarSwoole\Util
 */
class AnnotationAnalyser
{
    /**
     * 获取类属性注解信息
     * @param string $className 类名
     * @param array $annotationFilters 仅提取这些注解信息
     * @return array [属性名 => [注解名 => 注解值]]
     */
    public static function getAnnotations(string $className, array $annotationFilters = []): array
    {
        $data = [];
        $properties = self::properties($className);
        foreach ($properties as $property) {
            if ($annos = self::filter(self::extract($property->getDocComment()), $annotationFilters)) {
                $data[$property->name] = $annos;
            }
        }

        return $data;
    }

    /**
     * 从 doc 中抽取注解信息
     * @param string $doc
     * @return array
     */
    private static function extract(string $doc): array
    {
        preg_match_all('/@(.+)/', $doc, $matches);

        $data = [];
        foreach ($matches[1] as $annoStr) {
            $anno = explode(' ', $annoStr, 2);
            $data[trim($anno[0])] = isset($anno[1]) ? trim($anno[1]) : '';
        }

        return $data;
    }

    private static function properties(string $className)
    {
        return (new \ReflectionClass($className))->getProperties();
    }

    private static function filter(array $annotations, array $filters): array
    {
        return array_filter($annotations, function ($key) use ($filters) {
            return !$filters || in_array($key, $filters);
        }, ARRAY_FILTER_USE_KEY);
    }
}