<?php

namespace WecarSwoole\Client\Contract;

use Psr\Http\Message\ResponseInterface;
use WecarSwoole\Client\Response;

/**
 * 响应结果解析器，传入原始响应对象，该解析器解析成业务需要的格式
 * 注意：解析结果body必须是PHP数组
 * Interface IResponseParser
 * @package WecarSwoole\Client\Contract
 */
interface IResponseParser
{
    public function parser(string $url, ResponseInterface $response, bool $isRealRequest): Response;
}
