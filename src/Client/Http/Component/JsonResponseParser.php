<?php

namespace WecarSwoole\Client\Http\Component;

use Psr\Http\Message\ResponseInterface;
use WecarSwoole\Client\Config\Config;
use WecarSwoole\Client\Contract\IResponseParser;
use WecarSwoole\Client\Response\JsonArrayResponse;
use WecarSwoole\Client\Response;

class JsonResponseParser implements IResponseParser
{
    private $config;

    public function __construct(Config $config)
    {
        $this->config = $config;
    }

    public function parser(string $url, ResponseInterface $response, bool $isRealRequest): Response
    {
        return new JsonArrayResponse(
            $response->getBody()->read($response->getBody()->getSize()),
            $response->getStatusCode(),
            $response->getReasonPhrase(),
            $isRealRequest,
            $url
        );
    }
}
