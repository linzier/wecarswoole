<?php

namespace WecarSwoole\Http\Middlewares;

use App\ErrCode;
use EasySwoole\EasySwoole\Config;
use EasySwoole\Http\Request;
use EasySwoole\Http\Response;
use Lcobucci\JWT\Configuration;
use Lcobucci\JWT\Signer\Hmac\Sha256;
use Lcobucci\JWT\Signer\Key\InMemory;
use Lcobucci\JWT\Token;
use Lcobucci\JWT\UnencryptedToken;
use Lcobucci\JWT\Validation\Constraint\SignedWith;
use Lcobucci\JWT\Validation\Constraint\StrictValidAt;
use Lcobucci\Clock\FrozenClock;
use WecarSwoole\Exceptions\AuthException;
use WecarSwoole\Middleware\Next;
use WecarSwoole\Util\Encrypt;

/**
 * jwt 认证中间件
 */
class JWTAuthMiddleware implements IRouteMiddleware
{
    /**
     * @param Next $next
     * @param Request $request
     * @param Response $response
     * @return mixed
     * @throws \Exception
     */
    public function handle(Next $next, Request $request, Response $response)
    {
        // 可通过配置跳过校验（一般用来做临时测试用）
        $auth = intval(Config::getInstance()->getConf("jwt_auth_on") ?? 1);
        if (!$auth) {
            return $next($request, $response);
        }

        list($signKey, $secret) = $this->getKeys($request);

        if (!$signKey) {
            throw new AuthException("invalid invoke:jwt key required", ErrCode::PARAM_VALIDATE_FAIL);
        }

        if (!$tokenStr = $request->getHeader('authorization')[0] ?? '') {
            throw new AuthException("invalid invoke:Authorization header required", ErrCode::PARAM_VALIDATE_FAIL);
        }

        if ($secret) {
            // 需要对 payload 部分解密
            $tokenStr = $this->decryptToken($request, $secret, $tokenStr);
        }

        // jwt 认证
        if (!$token = $this->extractJWTToken($tokenStr, $signKey)) {
            throw new AuthException("jwt validate fail", ErrCode::AUTH_FAIL);
        }

        assert($token instanceof UnencryptedToken);

        // jwt 认证成功，将 payload 嵌入到请求参数中
        $pBody = $request->getParsedBody() ?? [];
        // 剔除关键字
        $pBody['__session__'] = array_filter(
            $token->claims()->all(),
            function ($key) {
                return !in_array($key, ['iss', 'exp', 'sub', 'aud', 'nbf', 'iat', 'jti']);
            },
            ARRAY_FILTER_USE_KEY
        );
        $request->withParsedBody($pBody);

        return $next($request, $response);
    }

    /**
     * @param string $token
     * @param string $key
     * @return bool
     * @throws \Exception
     */
    protected function extractJWTToken(string $token, string $key): ?Token
    {
        $config = Configuration::forSymmetricSigner(new Sha256(), InMemory::plainText($key));
        $config->setValidationConstraints(
            new SignedWith($config->signer(), $config->signingKey()),
            new StrictValidAt(
                new FrozenClock(new \DateTimeImmutable()),
                \DateInterval::createFromDateString("30 seconds")
            )
        );

        $token = $config->parser()->parse($token);
        if (!$config->validator()->validate($token, ...$config->validationConstraints())) {
            return null;
        }

        return $token;
    }

    /**
     * 解密 token
     * @param Request $request
     * @param string $secret
     * @param string $token
     * @return string
     * @throws AuthException
     */
    protected function decryptToken(Request $request, string $secret, string $token): string
    {
        $token = explode('.', $token);
        if (count($token) != 3) {
            throw new AuthException("invalid token format", ErrCode::PARAM_VALIDATE_FAIL);
        }

        $token[1] = Encrypt::dec($token[1], $secret);

        return implode('.', $token);
    }

    /**
     * 获取 jwt 签名和加密用的 key 和 secret
     * 如果没有提供 secret 则表示不加密
     * 应用程序中可以通过覆盖本方法提供应用程序自己的 key 和 secret 获取机制（比如根据 appid 从数据库/redis获取，实现为不同的第三方调用者分配不同的 key）
     * @param Request $request
     * @return array [$sign_key, $secret]
     */
    protected function getKeys(Request $request): array
    {
        $conf = Config::getInstance();
        // 从配置中心获取
        return [
            $conf->getConf('jwt_sign_key'),
            intval($conf->getConf('jwt_encrypt_on')) ? $conf->getConf('jwt_secret') : '',
        ];
    }
}
