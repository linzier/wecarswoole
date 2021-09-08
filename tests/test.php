<?php

use WecarSwoole\Client\Response;

include_once './base.php';

$resp = new Response(['status' => 300, 'info' => '处理失败'], 201, '网络出错');

echo "is ok:", $resp->isBusinessOk(),"\n";
echo "error:", $resp->getBusinessError(),"\n";