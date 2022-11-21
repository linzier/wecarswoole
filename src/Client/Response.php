<?php

namespace WecarSwoole\Client;

use WecarSwoole\ErrCode;

abstract class Response
{
    protected $body;
    protected $message;
    protected $status;
    protected $fromRealRequest; // 是否来自真正的请求，还是模拟的
    protected $url;// 本次请求的url（?前面的）
    protected $headers;

    public function __construct($body = '', $status = 200, $message = '', $fromRealRequest = true, $url = '', $headers = [])
    {
        $this->body = $body;
        $this->status = $status;
        $this->message = $message;
        $this->fromRealRequest = $fromRealRequest;
        $this->url = $url;
        $this->headers = $headers;
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

    /**
     * 获取接口返回的业务数据
     * 可指定获取哪个字段的数据
     * @param string $field
     * @return mixed
     */
    abstract public function getBody($field = '');

    public function getHeaders(): array
    {
        return $this->headers;
    }

    public function fromRealRequest(): bool
    {
        return $this->fromRealRequest;
    }

    /**
     * @return bool
     * @var bool 网络传输层是否 ok
     */
    public function isTransOk(): bool
    {
        return $this->status >= 200 && $this->status < 300;
    }

    /**
     * 是否网络超时
     * @return bool
     */
    public function isTimeout(): bool
    {
        if ($this->isTransOk()) {
            return false;
        }

        return in_array($this->status, [408, 504, -1, -2]);
    }

    /**
     * 业务处理层是否OK
     * 注意：此方法需先检查传输层，如果传输层OK，再检查业务层。业务层依据body里面的$statusField是否等于$statusVal
     * 其中$statusVal可以是数组或者简单值，如果是数据，则表示目标值只要在其中即OK，
     * 数组中可以用 min、max表示范围，如 ['min' => 200, 'max' => 299] 表示状态值在 200 到 299 之间（左右都包含）就 OK
     * @param string $statusField
     * @param int $statusVal
     * @return bool
     */
    abstract public function isBusinessOk(string $statusField = 'status', $statusVal = ErrCode::OK): bool;

    /**
     * 获取业务层的错误信息
     * 该方法一般和isBusinessOk配合使用
     * @param array $errorField
     * @return string
     */
    abstract public function getBusinessError($errorField = ['info', 'msg', 'error', 'message']): string;

    public function __toString()
    {
        return json_encode([
            'http_code' => $this->status,
            'message' => $this->message,
            'body' => $this->getBody(),
            'from_real_request' => intval($this->fromRealRequest)
        ]);
    }
}
