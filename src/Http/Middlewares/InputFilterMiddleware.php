<?php

 namespace WecarSwoole\Http\Middlewares;

 use EasySwoole\EasySwoole\Config;
 use EasySwoole\Http\Request;
 use EasySwoole\Http\Response;
 use voku\helper\AntiXSS;
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
        static $antiXss;

        if (!$antiXss) {
            $antiXss = new AntiXSS();
            $antiXss->removeEvilAttributes(['style']);
        }

        $xssFilter = boolval(intval(Config::getInstance()->getConf('xss_filter') ?? 0));
        $trimSpace = boolval(intval(Config::getInstance()->getConf('trim_whitespace') ?? 1));

        $action = basename(explode('?', $request->getRequestTarget())[0]);
        $xssEx = $this->proxy->xssExcludes();
        $xssEx = $xssEx[$action] ?? [];

        $p = [];
        foreach ($this->proxy->requestParams as $k => $val) {
            $p[$k] = $this->filter($val, $xssFilter && !in_array($k, $xssEx), $trimSpace, $antiXss);
        }
        $this->proxy->requestParams = $p;

        return $next($request, $response);
    }

    private function filter($input, bool $xssFilter, bool $trimSpace, AntiXSS $antiXss)
    {
        if (!$xssFilter && !$trimSpace || !$input || !is_string($input) && !is_array($input)) {
            return $input;
        }

        // 字符串
        if (is_string($input)) {
            return $this->innerFilter($input, $xssFilter, $trimSpace, $antiXss);
        }

        // 数组
        $out = [];
        foreach ($input as $k => $v) {
            $out[$k] = $this->filter($v, $xssFilter, $trimSpace, $antiXss);
        }

        return $out;
    }

    private function innerFilter($input, bool $xssFilter, bool $trimSpace, AntiXSS $antiXss)
    {
        if ($trimSpace) {
            $input = trim($input);
        }

        if ($xssFilter) {
            $input = html_entity_decode($antiXss->xss_clean($input));
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
