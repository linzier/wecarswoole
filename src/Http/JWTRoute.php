<?php

namespace WecarSwoole\Http;

use FastRoute\RouteCollector;
use WecarSwoole\Http\Middlewares\JWTAuthMiddleware;

/**
 * jwt 路由基类，这里的路由需走 jwt 认证
 * Class JWTRoute
 * @package WecarSwoole\Http
 */
abstract class JWTRoute extends Route
{
    public function __construct(RouteCollector $collector)
    {
        $this->appendMiddlewares(JWTAuthMiddleware::class);

        parent::__construct($collector);
    }
}
