<?php

use WecarSwoole\Client\Response;

include_once './base.php';

$resp = new Response(['status' => 300, 'info' => '处理失败', 'data' => [
    'person' => [
        'name' => '张三',
        'loves' => ['篮球', '足球']
    ]
]], 201, '网络出错');

var_export($resp->getBody('data.person.loves.0'));

