<?php

namespace WecarSwoole\Client;

use WecarSwoole\ErrCode;

class Response
{
    /**
     * @var array 解析器解析出来的body必须是数组
     */
    protected $body;
    protected $message;
    protected $status;
    protected $fromRealRequest; // 是否来自真正的请求，还是模拟的
    protected $url;// 本次请求的url（?前面的）

    public function __construct($body = [], $status = 500, $message = '请求出错', $fromRealRequest = true, $url = '')
    {
        $this->body = $body;
        $this->status = $status;
        $this->message = $message;
        $this->fromRealRequest = $fromRealRequest;
        $this->url = $url;
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
     * 支持获取指定的字段，可以用.获取多级字段，如‘data.person.name’表示获取$body['data']['person']['name']的值，如果没有则返回null
     * 不提供$field则返回整个body
     * @param string $field
     * @return array|mixed|null
     */
    public function getBody($field = '')
    {
        if (!is_array($this->body)) {
            return null;
        }

        if ($field) {
            if (!$this->body) {
                return null;
            }

            $field = explode('.', $field);
            $val = $this->body;
            foreach ($field as $key) {
                if (!isset($val[$key])) {
                    return null;
                }

                $val = $val[$key];
            }

            return $val;
        }

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
     * 注意：此方法先检查传输层，如果传输层OK，再检查业务层。业务层依据body里面的$statusField是否等于$statusVal
     * 其中$statusVal可以是数组或者简单值，如果是数据，则表示目标值只要在其中即OK，
     * 数组中可以用 min、max表示范围，如 ['min' => 200, 'max' => 299] 表示状态值在 200 到 299 之间（左右都包含）就 OK
     * @param string $statusField
     * @param int $statusVal
     * @return bool
     */
    public function isBusinessOk(string $statusField = 'status', $statusVal = ErrCode::OK): bool
    {
        if (!$this->isTransOk()) {
            return false;
        }

        // 如果body不是数组，则认为失败
        if ($this->body && !is_array($this->body)) {
            return false;
        }

        if (!is_array($statusVal)) {
            $statusVal = [$statusVal];
        }

        if (!$this->body || !isset($this->body[$statusField]) || !$this->bodyStatusOk($this->body[$statusField], $statusVal)) {
            return false;
        }

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
        if (!is_array($errorField)) {
            $errorField = [$errorField];
        }

        $prefix = "接口{$this->url}返回:";
        if (!$this->isTransOk()) {
            return $prefix . $this->message . "($this->status)";
        }

        if (!$this->body) {
            return $prefix . '未返回任何数据';
        }

        // 解析后仍然是字符串认为失败
        if (is_string($this->body)) {
            return $prefix . mb_substr($this->body, 0, 500);
        }

        foreach ($errorField as $field) {
            if (isset($this->body[$field])) {
                return $this->body[$field];
            }
        }

        return '';
    }

    private function bodyStatusOk($val, array $statusArr): bool
    {
        if (isset($statusArr['min']) && isset($statusArr['max'])) {
            // 表示范围
            return $val >= $statusArr['min'] && $val <= $statusArr['max'];
        }

        return in_array($val, $statusArr);
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
