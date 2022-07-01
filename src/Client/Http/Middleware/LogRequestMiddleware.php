<?php

namespace WecarSwoole\Client\Http\Middleware;

use EasySwoole\EasySwoole\Config;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;
use Swlib\Http\BufferStream;
use WecarSwoole\Client\Config\HttpConfig;
use WecarSwoole\Middleware\Next;

/**
 * 记录请求日志
 * Class LogRequestMiddleware
 * @package WecarSwoole\Client\Http\Middleware
 */
class LogRequestMiddleware implements IRequestMiddleware
{
    protected $logger;
    protected $startTime;

    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    public function before(Next $next, HttpConfig $config, RequestInterface $request)
    {
        $this->startTime = time();

        return $next($config, $request);
    }

    public function after(Next $next, HttpConfig $config, RequestInterface $request, ResponseInterface $response)
    {
        if (Config::getInstance()->getConf("api_invoke_log") == 'off') {
            return $next($config, $request, $response);
        }

        $rpBody = $response->getBody();
        $rpBody->rewind();
        $respStr = $rpBody->getContents();
        $rpBody->rewind();

        $uri = strval($request->getUri());
        $reqStr = $request->getBody()->getContents();
        $time = time() - $this->startTime;

        $msg = "API调用信息: request_url:{$uri}; request_raw_body:{$reqStr}; response_http_code:{$response->getStatusCode()}; "
            . "response_reason:{$response->getReasonPhrase()}; response_raw_body:{$respStr}; use_time:{$time}s";

        $this->logger->log($this->logLevel($response), $msg);

        return $next($config, $request, $response);
    }

    protected function logLevel(ResponseInterface $response): string
    {
        return $response->getStatusCode() >= 200 && $response->getStatusCode() < 400 ?
            LogLevel::INFO : LogLevel::CRITICAL;
    }
}
