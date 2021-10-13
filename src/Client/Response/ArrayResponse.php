<?php

namespace WecarSwoole\Client\Response;

use WecarSwoole\ErrCode;
use WecarSwoole\Client\Response;

/**
 * 返回的body转换成数组
 * Class ArrayResponse
 * @package WecarSwoole\Client
 */
abstract class ArrayResponse extends Response
{
    public function __construct($body = '', $status = 200, $message = '', $fromRealRequest = true, $url = '')
    {
        parent::__construct($body, $status, $message, $fromRealRequest, $url);

        // 对body decode
        $decbody = $this->decodeBody($body);
        if ($decbody === null) {
            // 解析失败
            $this->body = [];
            if ($body) {
                $this->message = $this->message ? $this->message . ',body str:' . $body : $body;
            }
        } else {
            $this->body = $decbody;
        }
    }

    /**
     * 解析body
     * @param string $origBody
     * @return mixed 正常情况下返回解析后的数组，解析失败返回null
     */
    abstract protected function decodeBody(string $origBody);

    /**
     * 获取接口返回的业务数据
     * 支持获取指定的字段，可以用.获取多级字段，如‘data.person.name’表示获取$body['data']['person']['name']的值，如果没有则返回null
     * 不提供$field则返回整个body
     * @param string $field
     * @return array|mixed|null
     */
    public function getBody($field = '')
    {
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

        if (!is_array($statusVal)) {
            $statusVal = [$statusVal];
        }

        if (!isset($this->body[$statusField]) || !$this->bodyStatusOk($this->body[$statusField], $statusVal)) {
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

        if (!$this->body && $this->message) {
            return $prefix . $this->message;
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
}
