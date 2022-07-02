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

如果开启 xxs 过滤导致某些字段出问题，可以设置对该字段不做 xss 处理，由应用层自己做特别处理：
```php
// 在控制器中设置下面的方法（覆盖基类的）
class YourController extends Controller
{
    protected function xssExcludes(): array
    {
        // 设置 myActionName 这个 action 的 name 和 loves 两个字段不做 xss 过滤，由应用自己处理
        return [
            'myActionName' => ['name', 'loves'],
        ];
    }
}
```
注意：只能设置一级字段，比如请求是如下的 json 格式（Content-Type:application/json）：
```json
{
  "loves": {
    "out": ["love1","love2"],
    "in": ["love3","love4"]
  }
}
```
在 xssExcludes() 中只能配 loves 不进行 xss 过滤，不能设置 loves 下面的 out 字段。

另外，框架用的 xss 过滤器在富文本环境未充分验证其正确性，如果因其过滤导致富文本出现问题，则可排除掉富文本字段，在业务中使用专门的富文本处理库 [ezyang/htmlpurifier](http://htmlpurifier.org) 去过滤。

2. 框架默认会对输入字符串做首尾去空格，可以在 apollo 中配置关闭：
```php
// config.php
...
'trim_whitespace' => apollo('application', 'trim_whitespace') ?? 1,
```

**注意：**控制器尽量只使用 $this->params(...) 的方式拿请求数据，上面的过滤仅在这种方式下有效，如果通过诸如 $this->request()->getParsedBody() 获取的，仍然存在 xss 风险。

3. 请求参数验证器：参见[控制器](./readme/controller.md)
