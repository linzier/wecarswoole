<?php

use WecarSwoole\Client\Response;

include_once './base.php';

$resp = new Response('错了', 201, '网络出错');

var_export($resp->getBusinessError());

