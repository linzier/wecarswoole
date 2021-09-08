<?php

namespace WecarSwoole\Client\Http\Component;

use WecarSwoole\Client\Config\Config;
use WecarSwoole\Client\Contract\IResponseParser;
use WecarSwoole\Client\Response;

class JsonResponseParser implements IResponseParser
{
    private $config;

    public function __construct(Config $config)
    {
        $this->config = $config;
    }

    public function parser(Response $response): Response
    {
        $result = json_decode($response->getBody(), true);
        if ($result !== null) {
            $response->setBody($result);
        }
        
        return $response;
    }
}
