<?php

namespace WecarSwoole;

/**
 * 错误码定义
 * 建议具体项目中集成该类并定义自己的错误码
 * 20* 表示成功
 * 30* 往后表示失败
 * 500 是系统默认错误码，未指定错误码的，默认为 500
 * Class ErrCode
 * @package WecarSwoole
 */
class ErrCode
{
    public const OK = 200;
    public const ERROR = 500;
    public const PARAM_VALIDATE_FAIL = 300;// 参数校验失败
    public const AUTH_FAIL = 301;// 鉴权失败
    public const CONC_EXEC_FAIL = 302;// 并发执行异常
    public const API_INVOKE_FAIL = 303;// 接口调用异常（可能是传输层异常也可能是业务处理异常）
}
