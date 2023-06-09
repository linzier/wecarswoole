### 外部 API 调用

对外部系统的 API 调用主要是通过配置来实现的，最简单的只需要配置 server 和 path信息即可，复杂的可以配置请求协议（目前仅支持 http(s)）、请求参数组装器、响应参数解析器、中间件等，实现定制化调用。配置好后在程序中调用 `\WecarSwoole\Client\API::invoke($apiName, $requestData, $config)` 即可。

其中 $apiName 是 api 别名而不是 uri。

API 是分组配置的，最佳实践是同一个接口提供方的 API 放在一组，而不同的提供方往往其请求参数组装方式、响应参数解析方式甚至是协议都不同，一般放到不同分组中。喂车内部接口（OS的例外）由于遵循相同的处理方式，可放到一组。 

> 为何要采用配置的方式使用 API？
> 1. 对项目使用到的外部 API 有统一的管理。
> 2. 采用配置的方式，容易修改（如 http 改成 https，修改 url 等）。
> 3. 程序中仅需要使用 API::invoke() 调用 API，无需关注协议以及其它细节。目前采用的是 HTTP 协议，后面可能某些接口会使用其它协议（如 RPC），此时并不需要调整程序。
> 4. 配置提供了统一的使用方式，并且提供了很好的扩展性，针对不同的第三方接口调用，仅需要扩展相应的请求组装器和响应解析器即可，无需单独写一整套程序。
> 5. 程序中使用 API 别名而不是直接使用 url，这对于服务注册中心是友好的。当使用注册中心时，程序中使用的是服务别名，而服务的域名、url 都是动态注册的，可能会变动的。

### 配置 api。在 config/api/ 中定义。如：

api.php:

```php
<?php

use WecarSwoole\Client\Http\Component\WecarHttpRequestAssembler;
use WecarSwoole\Client\Http\Component\JsonResponseParser;
use \WecarSwoole\Client\Http\Middleware\LogRequestMiddleware;

/**
 * 外部 api 定义
 * 可支持多种协议（典型如 http 协议，rpc 协议）
 * api 外部使用方式：group_name:apiname
 */
return [
    'config' => [
        // 请求协议
        'protocol' => 'http', // 支持的协议：http、rpc（尚未实现）
        // http 协议请求默认配置
        'http' => [
            // 请求参数组装器
            'request_assembler' => WecarHttpRequestAssembler::class,
            // 响应参数解析器
            'response_parser' => JsonResponseParser::class,
            // 请求中间件，必须实现 \WecarSwoole\Client\Http\Middleware\IRequestMiddleware 接口
            'middlewares' => [
                LogRequestMiddleware::class,
                MockRequestMiddleware::class
            ],
          	// 当返回非 20X 时是否抛异常
          	'throw_exception' => true,
            // https ssl 相关配置
            'ssl' => [
                // CA 文件路径
                'cafile' => '',
                // 是否验证服务器端证书
                'ssl_verify_peer' => false,
                // 是否允许自签名证书
                'ssl_allow_self_signed' => true
            ]
        ],
    ],
    // 组
    'weicheche' => include_once __DIR__ . 'weicheche.php',
];
```

weicheche.php:

```php
return [
    // 组公共配置，可覆盖 api.php 中的公共配置
    'config' => [
      	// 组 server 配置，底下 api 共用
		'server' => 'CP',
      	'http' => [

        ]
    ],
    // api 定义
    'api' => [
        'user.coupon.info' => [
            'desc' => '获取用户券信息',
            'server' => 'CP',// 可以是简写的，也可以是完整写法如 https://coupon.weicheche.cn,也可以是数组(从中取一个)
            // path 的格式支持占位符，如 users/{uid}，group/{?group_id} (?表示可选)，使用时根据传参替换
            'path' => '/usercoupons/getUserCoupons',
            'method' => 'POST', // http 协议下，请求方式
            'protocol' => 'http',
        ],
        'merchant.users.list' => [
            'desc' => '获取商户用户列表',
            'server' => 'UC',
            'path' => '/v1/merchants/{merchant}/users',
            'method' => 'GET',
        ],
        'users.add' => [
            'desc' => '添加用户',
            'server' => 'http://localhost:9501',
            'path' => '/v1/users',
            'method' => 'POST',
        ]
    ]
];
```

> 配置的四个级别：
>
> - 全局，在 api.php 中配置的；
> - 组级别，在各组中配置的；
> - api 级别，在单个 api 中配置的；
> - 调用级别，在调用时传入；

### 调用：

```php
use WecarSwoole\Client\API;
...
$result = API::invoke('wc:users.add', $reqData, $config); // $config 提供调用级别配置，结构同 api.php 中的配置
var_export($result->getBody());
```

API 目前仅支持 http 协议，但是可扩展的（比如支持 RPC 协议），api 具体用的什么协议是在配置文件中配置的，调用的时候不用管。

支持针对不同的分组或者单个 API 配置不同的请求参数组装器和响应参数解析器，这对于和第三方合作是很有用的，比如我们可以针对不同的第三方配置不同的分组，这些分组有各自的组装器和解析器实现。

系统对自身的 API 调用也需要在此处配置。

### 便捷调用：
`API::invoke()`要求先在配置文件中配好 API 信息，一般情况下我们推荐这样做（为了可维护性），但有时我们要调用的 url 是动态的（如数据库配置的），此时无法
做成静态配置。
框架提供了另一种调用方式：`API::simpleInvoke()`，该接口支持传绝对 url 而无需事先配置。用法：

```php
use WecarSwoole\Client\API;

// API::simpleInvoke(string $url, string $method = 'GET', array $params = [], string $group = '_', array $config = [])
$result = API::simpleInvoke("https://www.baidu.com");
var_export($result->getBody());
```
该接口和 `API::invoke()` 是兼容的，内部也是通过 `API::invoke()` 实现的。

### 失败重试：

`invoke` 和`simpleInvoke` 默认只会请求一次远程接口，调用超时会返回相应的错误。

可以在 $config 配置选项中指定 `retry_num` 参数要求进行失败重试（设定为 [0, 5] 之间的整数值），该值最大为 5。

默认情况下，每次重试前会 sleep（协程版）一定的时间，sleep 的时长跟重试次数相关，可以通过 `retry_func`替换默认规则。

```php
API::invoke('wc:users.add', $reqData, ["retry_num" => 2]);// 注意：retry_num 表示重试次数，实际请求次数为 retry_num+1
```

自定义 sleep 机制：

```php
API::invoke('wc:users.add', $reqData, [
	"retry_num" => 2,
	"retry_func" => function ($retriedNum) {
		return $retriedNum * 5;// 随着重试次数增加，sleep 时间增加。return 0 将不休眠，立即重试
	}
]);
```

系统默认 sleep 机制：

```php
$config['retry_func'] = function ($retriedNum) {
	return pow($retriedNum, 2) * 3;
};
```

**便捷调用：**

API 类提供了`retryInvoke`和`retrySimpleInvoke`供便捷方法，这些方法内置重试机制，其中`retry_num`取 api.php 中的配置项`default_retry_num`，`retry_func`取系统默认方法。

```php
API::retryInvoke('wc:users.add', $reqData);
```

注：`retryInvoke`和`retrySimpleInvoke`的参数分别和`invoke`和`simpleInvoke`相同。

api.php 的配置：

```php
return [
    'config' => [
        'protocol' => 'http', // 支持的协议：http、rpc（尚未实现）
        'http' => [
        	...
        ],
        // 当启动重试机制时，默认重试次数
        'default_retry_num' => 2,
    ],
    // 组
    'weicheche' => include_once __DIR__ . '/weicheche.php'
];
```

### 默认实现：

   框架默认提供了 `DefaultHttpRequestAssembler`、`WecarHttpRequestAssembler`、`WecarWithNoZipHttpRequestAssembler`、 `JsonResponseParser` 作为请求参数、响应参数的解析器，项目可以实现自己的。

   - `DefaultHttpRequestAssembler`：使用此请求解析器时，API::invoke传参格式：

     ```php
     $params = [
         'oilstation_id' => 172073,
         'uid' => 21343,
     ];
     ```

     或者：

     ```php
     $params = [
         // 请求头中的 Cookie 信息
         'cookies' => [
             'session_id' => '424dkjnt33fdew320fooee',
         ],
         // Header 头部
         'headers' => [
             'Auth-Token' => 'ah2jj2hb20djeyqmkiag476242',
         ],
         // query string(url 中 ? 后面的部分)
         'query_params' => [
             'flag' => 'wx'
         ],
       	// flag_params 是 RESTful API 中占位符替换的内容
         'flag_params' => [
             'uid' => 33433
         ],
         // POST 等的 body 内容（如果是 GET，则合并到 query_params 中）
         'body' => [
             'oilstation_id' => 172073,
         ]
     ];
     ```

### 中间件：

   实现 `WecarSwoole\Client\Http\Middleware\IRequestMiddleware` 接口，然后在配置文件中使用。

   注意：在中间件中操作了请求的 BufferStream （如read、getContents）需要将内容重新设置回去，否则后面就拿不到里面的东西了（对 Buffer 的读取会清空 Buffer）。

### Mock:

   很多时候需要跟第三方合作开发时，对方的接口尚未开发完毕，此时我们只能干等。

   框架通过中间件提供了 Mock 功能，模拟外部 API 数据。

   - 在项目根目录下的 mock/http/ 中创建 mock 文件（可按照需要命名），其中写 mock 数据：

   ```php
   use WecarSwoole\Util\Mock;
   use WecarSwoole\Client\Config\HttpConfig;
   use Psr\Http\Message\RequestInterface;
   use Swoole\Coroutine as Co;
   
   $mock = new Mock();
   
   return [
       /**
        * 完整返回格式(完整格式必须至少同时有 http_code 和 body)：
        *      [
        *          'http_code' => 200, // http code
        *          'body' => ... // http body，数组或者字符串，或者其他实现了 __toString() 的对象
        *          'headers' => [], // http 响应头
        *          'activate' => 1, // 激活，0表示不再使用该 mock 数据，将请求真实数据
        *      ]
        * 注意：如果直接返回数组，则多次使用的是同一份模拟数据，如果想每次都随机生成不同的，需要使用匿名函数
        *
        */
       'weiche:oil.info' => function (HttpConfig $config, RequestInterface $request) use ($mock) {
           // 此处模拟响应延迟
           Co::sleep(5);
   
           return [
               'http_code' => 200,
               'body' => [
                   'status' => 200,
                   'data' => [
                       'id' => $mock->number('100-10000'),
                       'name' => $request->getParams()['name'],
                       'age' => $mock->number('10-20'),
                   ]
               ],
               'activate' => true
           ];
       },
       // 直接返回数据
       'weiche:user.add' => [
           'status' => 200,
           'msg' => 'ok',
           'data' => [
               'uid' => 123122
           ]
       ]
   ];
   ```

> 注：实际使用中，一般会将对外部系统的调用封装成服务类（Service），服务类调用具体接口并返回相应的数据（基本数据类型或者自定义类型）。

### 返回对象:
API类相关方法都是返回`WecarSwoole\Client\Response`，该对象在内部已经通过`IResponseParser::parser(...)`解析过body(IResponseParser将接口提供方的响应数据解析成PHP array)。

`Response`的典型用法：
```php
// 带重试机制的调用
$resp = API::retryInvoke('wecar:order.pay', $params);
// 调isBusinessOk方法判断是否处理成功。此方法会从HTTP传输层和业务层判断是否成功（方法可指定业务层响应体里面的状态字段和状态码，默认是status和200）
if (!$resp->isBusinessOk()) {
    // 如果处理失败，抛出异常（根据具体场景需要），带上错误原因
    throw new InvokeException($resp->getBusinessError());
}
// 通过getBody获取需要的值。该方法支持点号获取深层值，不传参数则表示获取整个body。获取失败返回null
$tradeNo = $resp->getBody('data.trade_no');
...
```

[返回](../README.md)