<?php

use WecarSwoole\Client\Http\Component\WecarHttpRequestAssembler;
use WecarSwoole\Client\Http\Component\JsonResponseParser;

/**
 * 喂车内部子系统 api 定义
 */
return [
    'config' => [
        'http' => [
            // 请求参数组装器
            'request_assembler' => WecarHttpRequestAssembler::class,
            // 响应参数解析器
            'response_parser' => JsonResponseParser::class,
        ]
    ],
    // api 定义
    'api' => [
        // 不要删这个，框架告警短信用到
        'sms.send' => [
            'server' => 'DX',
            'path' => 'v1.0/sms/send',
            'method' => 'POST'
        ],
        // 如果要用到 sso 登录，请不要删这个，框架 sso 登录需要用到
        'sso.login' => [
            'server' => 'DL',
            'path' => '/v1/ticket/verify',
            'method' => 'GET'
        ],
        // 如果要用到 sso 登录，请不要删这个，框架 sso 登录需要用到
        'sso.logout' => [
            'server' => 'DL',
            'path' => '/v1/logout',
            'method' => 'POST'
        ],
    ]
];
