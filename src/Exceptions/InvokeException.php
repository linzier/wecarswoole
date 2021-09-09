<?php

namespace WecarSwoole\Exceptions;

use Throwable;
use WecarSwoole\ErrCode;

/**
 * 接口调用异常
 * 和APIInvokeException不同，此异常表示出现了传输异常或者业务处理异常
 */
class InvokeException extends \Exception
{
    public function __construct($message = "", $code = ErrCode::API_INVOKE_FAIL, Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
