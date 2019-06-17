<?php

namespace WecarSwoole\Middleware;

use EasySwoole\Http\Request;
use EasySwoole\Http\Response;

/**
 * 路由中间件接口
 * Interface IRouteMiddleware
 * @package WecarSwoole\Middleware
 */
interface IRouteMiddleware
{
    /**
     * 抛出异常或者返回 Response 对象则终止请求执行
     * @param Request $request
     * @param Response $response
     * @return mixed
     */
    public function handle(Request $request, Response $response);
}
