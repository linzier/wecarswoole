### JWT 认证适用场景
1. 前后端分离情况下的登录与会话保持；
2. 第三方调动时的身份认证；

### JWT 介绍
参见[官网](https://jwt.io/introduction)。

### 其他介绍文章
参见[接口设计的那些事](https://www.cnblogs.com/linvanda/p/16053236.html)，本文详细介绍了接口设计的方方面面，其中涉及到前后端安全交互的章节有讨论 JWT 和前后端分离情况下的登录认证流程。

### 框架提供的 JWT 认证特点
1. 基于 HTTP header 传递 token，防止 CSRF 攻击；
1. 简单易用。前端只需要将后端接口响应头中的 Auth-Token 头保存起来，请求后端接口时带上该头部值（放在 Authorization 请求头）即可实现认证与会话保持；
1. JWT 认证是无状态的，后端无需存储会话（session）；
1. 和单点登录系统（sso）深度集成，一行代码即可实现基于公司的 sso 系统登录；
1. 支持对 JWT Payload 部分加密，以保存敏感信息（默认情况下不加密）；

### 前后端分离下实现登录
以下分自实现登录和基于 sso 登录两种情况讨论。

#### 自实现登录：
由项目自己实现登录逻辑（如通过用户名密码、手机验证码、企业微信 OAuth2.0 授权等）。

**步骤：**
1. 后端：在 config.php 中加入如下配置(config.example.php 中有)：

```php
// 是否开启 jwt 认证（继承 JWTRoute 的接口）
'jwt_auth_on' => apollo('application', 'jwt_auth_on') ?? 1,
// jwt 签名用的 key，需要保证足够的强度
// PHP7 以上建议用 bin2hex(random_bytes(32)) 生成
'jwt_sign_key' => apollo('application', 'jwt_sign_key') ?? '',
// jwt token 过期时间
'jwt_expire' => apollo('application', 'jwt_expire') ?? 3600 * 3,
```

1. 后端：配置路由。需要 jwt 鉴权的路由需继承 `WecarSwoole\Http\JWTRoute`，如：
```php
<?php

namespace App\Http\Routes;

use WecarSwoole\Http\Route;

/**
* 普通路由，继承 Route
 */
class NoAuthAPI extends Route
{
    public function map()
    {
        // 登录接口
        $this->post("/v1/login", "/V1/Session/login");
        // 通过 sso 系统实现登录
        $this->post("/v1/sso/login", "/V1/Session/ssoLogin");
    }
}
```

```php
<?php

namespace App\Http\Routes;

use WecarSwoole\Http\JWTRoute;

/**
* 需要 jwt 鉴权的路由，继承 JWTRoute
 * 也就是说这些接口都是要登录后才能访问的
 * JWTRoute 内部使用了 JWTAuthMiddleware 中间件实现 jwt 认证
 */
class JWTAPI extends JWTRoute
{
    public function map()
    {
        $this->get("/v1/user", "/V1/User/userInfo");
        $this->post("/v1/user", "/V1/User/addUser");
        $this->post("/v1/logout", "/V1/Session/logout");
        $this->post("/v1/sso/logout", "/V1/Session/ssoLogout");
    }
}
```

1. 后端：开发登录、登出接口：
```php
<?php
...

class Session extends Controller
{
    // 实现登录接口
    public function login()
    {
        $account = $this->params('account');
        $pwd = $this->params('pwd');

        // 实现登录验证逻辑
        if ($account != 'test' || $pwd != '123456') {
            throw new \Exception("login fail", 300);
        }

        // 登录成功，生成会话
        $user = ['uid' => $userId, 'account' => $account, 'name' => '张安',];
        // 保存 session
        // 内部会生成 jwt token 并写入到 Auth-Token 响应头
        $this->session($user);
        // 返回用户基本信息给前端
        $this->return($user);
    }
    
    // 实现登出接口
    // 照抄代码即可
    public function logout()
    {
        // 销毁会话
        // 内部会将 Auth-Token 响应头设置为空
        $this->destroySession();
        $this->return();
    }
    
    // 通过 sso 实现登录
    // 照抄代码即可
    public function ssoLogin()
    {
        $user = $this->ssoLogin($this->params('code'));
        $this->return($user);
    }

    // 通过 sso 实现登出
    // 照抄代码即可
    public function ssoLogout()
    {
        $this->ssoLogout();
        $this->return();
    }
}
```

1. 前端：实现登录页面，用户登录，调后端登录接口（如 POST http://domain.com/v1/login）；
1. 前端：上一步调的后端的登录接口登录成功后，会返回用户基本信息，前端将该信息缓存在本地供后面使用；另外登录接口还会返回 Auth-Token 响应头，前端要把响应头的信息保存起来供后面使用；
1. 前端：前端后续调后端所有需认证的接口都要带上 Authorization 请求头，值取上一步响应头 Auth-Token 的值；
1. 前端：注意：只要后端响应头有 Auth-Token，前端就要判断：如果 Auth-Token 的值是空，则说明退出登录了（会话过期或者主动登出），此时前端需要清除缓存的用户信息和 Auth-Token 信息，并跳到登录页面；
1. 前端：后端的所有需认证的接口不但在请求时需要带上 Authorization 请求头，这些接口也一定会带上 Auth-Token 响应头，如果该值为空说明登录失效了，如果不为空，则前端你下一次请求时需要用该 Auth-Token 的值作为 Authorization 请求头的值；
1. 前端：Auth-Token 每次都会更新，所以前端总是要用最新的 Auth-Token。该刷新机制的目的是为了保证在用户正常访问的情况下不会出现突然 session 过期；
1. 前端：退出登录：调后端退出登录接口（如 POST http://domain.com/v1/logout），该接口同样会返回 Auth-Token，值是空字符串（说明会话被销毁了）；

#### 通过 sso 登录：
参见 [sso 系统](http://showdoc.wcc.cn/web/#/48?page_id=994) 了解前后端分离（以及移动端）场景下如何实现基于 sso 的登录。

1. 后端：jwt 相关配置同上。需增加 sso 相关配置（框架已经生成好了，检查下如果有则无需配置了）：
```php

// 注意：config/api/api.php 中的 'weicheche' => include_once __DIR__ . '/weicheche.php' 这行代码不要动，框架要用到 weicheche 这个组
// 在 config/api/weicheche.php 文件中需要如下配置
...
// 如果要用到 sso 登录，请不要删这个，框架 sso 登录需要用到
'sso.login' => [
    'server' => 'DL',
    'path' => '/v1/ticket/verify',
    'method' => 'GET'
],
// 如果要用到 sso 登录，请不要删这个，框架 sso 登录需要用到
'sso.logout' => [
    'server' => 'DL',
    'path' => '/v1/logout',
    'method' => 'POST'
],
```
1. 后端：写好登录、登出接口（参见前面的示例代码）；
1. 前端：从 sso 系统拿到 code（参见 sso 系统）；
1. 前端：调后端登录接口（如 POST http://domain.com/v1/sso/login），根据 code 实现登录；
1. 前端：将后端登录接口返回的用户基本信息以及 Auth-Token 响应头缓存起来供后面用；
1. 前端：调其他接口时带上 Authorization 请求头，值取前面 Auth-Token 的值；
1. 前端：如果后端返回的 Auth-Token 为空，说明会话失效，需清除本地缓存并跳转到登录页面；
1. 前端：退出登录：调后端相关接口即可；

#### 总结：
对于前端来说：
1. 请求登录接口获取用户基本信息和 Auth-Token 响应头；
1. 如果 Auth-Token 为空说明会话失效，需要重新登录；
1. 请求需认证的接口时必须带 Authorization 请求头，其值就是 Auth-Token 响应头的值；

对于后端来说：
config.php 中的配置：
```php
// 是否开启 jwt 认证（继承 JWTRoute 的接口）
'jwt_auth_on' => apollo('application', 'jwt_auth_on') ?? 1,
// jwt 签名用的 key，需要保证足够的强度
// PHP7 以上建议用 bin2hex(random_bytes(32)) 生成
'jwt_sign_key' => apollo('application', 'jwt_sign_key') ?? '',
// jwt token 过期时间
'jwt_expire' => apollo('application', 'jwt_expire') ?? 3600 * 3,
```

Controller 中相关方法：
```php
<?php

class Controller
{
    ...
    // 获取或者设置 session
    // session 值是存在 jwt token 中的，不要在 session 里面存太多东西，一般只存用户基本信息
    protected function session($keyOrVals = '', $val = null)
    {
        ...
    }
    
    // 删除某个 session 值
    protected function deleteSession(string $key)
    {
        ...
    }
    
    // 基于 sso 实现登录
    protected function ssoLogin(string $ssoCode): array
    {
        ...
    }
    
    // 基于 sso 实现退出登录
    protected function ssoLogout()
    {
        ...
    }
    
    // 销毁本地 session 会话
    // 自实现登录逻辑是需要调该方法退出登录
    protected function destroySession()
    {
        ...
    }
}
```

### 第三方调用 jwt 认证：

> 第三方调用建议走 API 网关，由网关层实现认证。

配置同上。

第三方调接口的时候带上 Authorization 请求头。

不同的是：每个第三方一般要有自己的 appid 和 secret，所以不能在配置文件中写死 secret，而是要用数据库/Redis 保存。

因而第三方 jwt 认证不能用默认的 `WecarSwoole\Http\Middlewares\JWTAuthMiddleware`认证中间件，要继承该类实现自己的中间件，从而实现自己的路由器。如：
```php

...
// 实现自定义认证中间件
class MyJWTAuthMiddleware extends JWTAuthMiddleware
{
    // 重写获取 key 的方法
    protected function getKeys(Request $request): array
    {
        
        // 从数据库等地方根据 $request 中的 appid 获取 key
        ...
        
        return [$signKey, ""];
    }
} 
```

实现自己的路由器（放在 app/Http/Routes/ 文件夹下）：
```php
...
// 定义自己的路由器，使用上面的中间件
class ThirdJWTRoute extends Route
{
    public function __construct(RouteCollector $collector)
    {
        $this->appendMiddlewares(MyJWTAuthMiddleware::class);

        parent::__construct($collector);
    }
    
    public function map()
    {
        ...
    }
}
```

### 加密

默认情况下，jwt 的 payload 部分是明文的，不可存放敏感信息（如用户密码）。

如果非要存放敏感信息，需要启用 jwt 加密。

配置：

```php
// 是否对 jwt 的 payload 加密。需配合 jwt_secret 使用，没提供 jwt_secret 则不加密
'jwt_encrypt_on' => apollo('application', 'jwt_encrypt_on') ?? 0,
// jwt 内容加密用的 secret，留空则不加密
'jwt_secret' => apollo('application', 'jwt_secret') ?? '',
```

在 apollo 配置好上面两个参数即可。

系统使用 AES-128-CBC 对称加密算法加解密的。

### 在控制器中获取会话上下文信息：
在控制器中通过`$this->session($key)`获取会话信息（如 $this->session('uid') 获取登录用户的 id）。
单点登录模式下，session 中默认有下面这些字段：
```php
[
    'uid' => $loginerId,
    'account' => $account,
    'name' => $name,
    'phone' => $phone,
];
```
程序中可以用 `$this->session($key, $val)` 设置 session 信息，如 `$this->session('user_sex', '男')`。
因为 session 数据是存在 jwt token 中，放在 HTTP Header 中的，不要在 session 中存太多东西，session 中只放登录用户的基本信息，其它信息要通过数据库或 Redis 获取。

**为何只能在控制器中通过 $this->session() 操作 session？**

做此限制是为了防止 session 污染。大部分框架（包括 PHP 自身）都提供了全局变量或函数操作 session，结果是到处都能看到 session 的身影（控制器、Service、Logic、Model 中），使得代码维护非常困难。

### key 和 secret 的强度

不能太简单。PHP7 以上建议用 bin2hex(random_bytes(32)) 生成。或者网上在线工具生成皆可。
