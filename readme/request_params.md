### 使用：

在 Controller 中通过 `$this->params()` 获取请求参数，通过 `$this->params($keyname)` 获取请求参数 $keyname 的值。

**$this->params() 能拿到的参数：**

url query string + 根据 content-type 解析出的 body 的值（数组格式）。

**body 的解析：**
框架根据请求的 content-type 头自动将原始 body 字符串解析成相应的 array：

- application/json：使用 json_decode 将 raw body data 解析成 array；
- application/xml：使用 simplexml_load_string 将 xml string 解析成 object，再通过 json_decode(json_encode($object), true) 将 object 转成 array；
- multipart/form-data、application/x-www-form-urlencoded：将 kv 转成 array。注意：此情况下，如果有"data"这个 key，则会将 data 里面的值作为真正的 data（内部很多系统将数据包装在 data 中）。

**获取原始 body 数据：**
绝大部分情况下通过 $this->params() 就能拿到请求参数，万一该函数拿不到（或者拿到的不正确），可以通过 $this->getRawBody() 获取原始 body 字符串。

**请求参数的过滤：**
1. 框架提供了 XSS 过滤功能，在 apollo 中配置开启即可（默认未开启）：

```php
// config.php
...
'xss_filter' => apollo('application', 'xss_filter') ?? 0,
```

对于管理后台项目强烈建议开启，防止 XSS 攻击；对于仅提供给其他系统调用的服务，可不开启；

2. 框架默认会对输入字符串做首尾去空格，可以在 apollo 中配置关闭：
```php
// config.php
...
'trim_whitespace' => apollo('application', 'trim_whitespace') ?? 1,
```

**注意：**控制器尽量只使用 $this->params(...) 的方式拿请求数据，上面的过滤仅在这种方式下有效，如果通过诸如 $this->request()->getParsedBody() 获取的，仍然存在 xss 风险。
