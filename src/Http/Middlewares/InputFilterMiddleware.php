<?php

 namespace WecarSwoole\Http\Middlewares;

 use EasySwoole\EasySwoole\Config;
 use EasySwoole\Http\Request;
 use EasySwoole\Http\Response;
 use HTMLPurifier;
 use HTMLPurifier_Config;
 use WecarSwoole\Http\Controller;
 use WecarSwoole\Middleware\Middleware;
 use WecarSwoole\Middleware\Next;

 /**
  * 过滤请求参数
  * Class InputFilterMiddleware
  * @package WecarSwoole\Http\Middlewares
  */
class InputFilterMiddleware extends Middleware implements IControllerMiddleware
{
    public function __construct(Controller $controller)
    {
        parent::__construct($controller);
    }

    public function before(Next $next, Request $request, Response $response)
    {
        $xssFilter = boolval(intval(Config::getInstance()->getConf('xss_filter') ?? 0));
        $trimSpace = boolval(intval(Config::getInstance()->getConf('trim_whitespace') ?? 1));

        $purifier = new HTMLPurifier(HTMLPurifier_Config::createDefault());

        $this->proxy->requestParams = $this->filter($this->proxy->requestParams, $xssFilter, $trimSpace, $purifier);

        return $next($request, $response);
    }

    private function filter($input, bool $xssFilter, bool $trimSpace, HTMLPurifier $purifier)
    {
        if (!$xssFilter && !$trimSpace || !$input || !is_string($input) && !is_array($input)) {
            return $input;
        }

        // 字符串
        if (is_string($input)) {
            return $this->innerFilter($input, $xssFilter, $trimSpace, $purifier);
        }

        // 数组
        $out = [];
        foreach ($input as $k => $v) {
            $out[$k] = $this->filter($v, $xssFilter, $trimSpace, $purifier);
        }

        return $out;
    }

    private function innerFilter($input, bool $xssFilter, bool $trimSpace, HTMLPurifier $purifier)
    {
        if ($trimSpace) {
            $input = trim($input);
        }

        if ($xssFilter) {
            $input = $purifier->purify($input);
        }

        return $input;
    }

    public function after(Next $next, Request $request, Response $response)
    {
        return $next($request, $response);
    }

    public function gc(Next $next)
    {
        return $next();
    }
}
