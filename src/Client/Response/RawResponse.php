<?php

namespace WecarSwoole\Client\Response;

use WecarSwoole\ErrCode;
use WecarSwoole\Client\Response;

class RawResponse extends Response
{
    /**
     * 获取原始 body
     * @param string $field
     * @return mixed
     */
    public function getBody($field = '')
    {
        return $this->body;
    }

    /**
     * 业务层总是返回 OK
     * @param string $statusField
     * @param int $statusVal
     * @return bool
     */
    public function isBusinessOk(string $statusField = 'status', $statusVal = ErrCode::OK): bool
    {
        return true;
    }

    /**
     * 获取业务层的错误信息
     * 该方法一般和isBusinessOk配合使用
     * @param array $errorField
     * @return string
     */
    public function getBusinessError($errorField = ['info', 'msg', 'error', 'message']): string
    {
        return '';
    }
}
