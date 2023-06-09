<?php
namespace PHPSTORM_META
{
    $STATIC_METHOD_TYPES = [
        \Psr\Container\ContainerInterface::get('') => [
            "" == "@",
        ],
        \DI\Container::get('') => [
            "" == "@",
        ],
        \WecarSwoole\Container::get('') => [
            "" == "@",
        ],
        \WecarSwoole\Container::make('', $params = []) => [
            "" == "@",
        ],
        \WecarSwoole\LazyProxy\Proxy::entity('', ...) => [
            "" == "@",
        ],
        \WecarSwoole\LazyProxy\Proxy::wrap('', ...) => [
            "" == "@",
        ],
    ];
}