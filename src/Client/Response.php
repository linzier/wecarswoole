<?php

namespace WecarSwoole\Client;

use WecarSwoole\ErrCode;

class Response
{
    protected $body;
    protected $message;
    protected $status;
    protected $fromRealRequest; // 是否来自真正的请求，还是模拟的

    public function __construct($body = [], $status = 500, $message = '请求出错', $fromRealRequest = true)
    {
        $this->body = $body;
        $this->status = $status;
        $this->message = $message;
        $this->fromRealRequest = $fromRealRequest;
    }

    public function getMessage()
    {
        return $this->message;
    }

    public function setMessage(array $message): void
    {
        $this->message = $message;
    }

    /**
     * 注意此 status 是协议层状态码，业务层定义的状态码（如果有）是在 body 中
     * @return int
     */
    public function getStatus(): int
    {
        return $this->status;
    }

    public function setStatus(int $status): void
    {
        $this->status = $status;
    }

    public function getBody()
    {
        return $this->body;
    }

    public function setBody($body)
    {
        $this->body = $body;
    }

    public function fromRealRequest(): bool
    {
        return $this->fromRealRequest;
    }

    /**
     * @var bool 网络传输层是否 ok
     */
    public function isTransOk(): bool
    {
        return $this->status >= 200 && $this->status < 300;
    }

    /**
     * 业务处理层是否OK
     * 注意：此方法先检查传输层，如果传输层OK，再检查业务层。业务层依据body里面的$statusField是否等于$statusVal
     * 其中$statusVal可以是数组或者简单值，如果是数据，则表示目标值只要在其中即OK
     */
    public function isBusinessOk(string $statusField = 'status', $statusVal = ErrCode::OK): bool
    {
        if (!$this->isTransOk()) {
            return false;
        }

        if (!is_array($statusVal)) {
            $statusVal = [$statusVal];
        }

        if (!$this->body || !isset($this->body[$statusField]) || !in_array($this->body[$statusField], $statusVal)) {
            return false;
        }

        return true;
    }

    /**
     * 获取业务层的错误信息
     * 该方法一般和isBusinessOk配合使用
     */
    public function getBusinessError($errorField = ['info', 'msg', 'error', 'message']): string
    {
        if (!is_array($errorField)) {
            $errorField = [$errorField];
        }

        if (!$this->isTransOk()) {
            return $this->message;
        }

        if (!$this->body) {
            return '接口方未返回任何数据';
        }

        foreach ($errorField as $field) {
            if (isset($this->body[$field])) {
                return $this->body[$field];
            }
        }

        return '';
    }

    public function __toString()
    {
        return json_encode([
            'http_code' => $this->status,
            'message' => $this->message,
            'body' => $this->body,
            'from_real_request' => intval($this->fromRealRequest)
        ]);
    }
}
