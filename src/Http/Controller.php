<?php

namespace WecarSwoole\Http;

use DateTimeImmutable;
use EasySwoole\EasySwoole\Config;
use Lcobucci\JWT\Configuration;
use Lcobucci\JWT\Signer\Hmac\Sha256;
use Lcobucci\JWT\Signer\Key\InMemory;
use Psr\Log\LoggerInterface;
use EasySwoole\Http\AbstractInterface\Controller as EsController;
use WecarSwoole\Client\API;
use WecarSwoole\Container;
use WecarSwoole\ErrCode;
use WecarSwoole\RedisFactory;
use WecarSwoole\Middleware\MiddlewareHelper;
use WecarSwoole\Exceptions\{EmergencyErrorException, CriticalErrorException, Exception, ValidateException};
use WecarSwoole\Http\Middlewares\{InputFilterMiddleware,
    LockerMiddleware,
    RequestRecordMiddleware,
    RequestTimeMiddleware,
    ValidateMiddleware};
use Dev\MySQL\Exception\DBException;
use EasySwoole\Validate\Validate;
use WecarSwoole\Util\Encrypt;

/**
 * 控制器基类
 * 不要在基类控制器写很多代码，建议抽离成单独的类处理
 * 禁止在基类控制器中写 public 方法，会造成后面难以维护
 * Class Controller
 * @package WecarSwoole\Http
 */
class Controller extends EsController
{
    use MiddlewareHelper;

    protected $responseData;
    protected $requestParams;
    protected $_session;

    /**
     * Controller constructor.
     * @throws \Throwable
     * @throws \WecarSwoole\Exceptions\ConfigNotFoundException
     */
    public function __construct()
    {
        $this->appendMiddlewares(
            [
                new LockerMiddleware($this),
                new RequestRecordMiddleware(),
                new InputFilterMiddleware($this),
                new ValidateMiddleware($this),
                new RequestTimeMiddleware(RedisFactory::build('main'), Container::get(LoggerInterface::class)),
            ]
        );

        parent::__construct();
    }

    /**
     * 并发锁定义，定义用哪些请求信息生成锁的 key。默认采用"客户端 ip + 请求url + 请求数据"生成 key
     * 格式：[请求action=>[请求字段数组]]。
     * 注意，如果提供了该方法，默认是严格按照该方法的定义实现锁的，即如果请求action没有出现在该方法中，就不会加锁，
     * 除非加上 '__default' => 'default'，表示如果没有出现在该方法中，就使用默认策略加锁（客户端 ip + 请求url + 请求数据）。
     * 例：
     * // 只有 addUser 会加锁：
     * [
     *      'addUser' => ['phone', 'parter_id']
     * ]
     * // addUser 按照指定策略加锁，其它 action 按照默认策略加锁
     * [
     *      'addUser' => ['phone', 'parter_id'],
     *      '__default' => 'default'
     * ]
     * // addUser 不加锁，其它按照默认策略加锁
     * [
     *      'addUser' => 'none',
     *      '__default' => 'default'
     * ]
     */
    protected function lockerRules(): array
    {
        return [];
    }

    /**
     * 验证器规则定义
     * 格式同 easyswoole 的格式定义，如
     * [
     *      // action
     *      'addUser' => [
     *          // param-name => rules
     *          'user_flag' => ['alpha', 'between' => [10, 20], 'length' => ['arg' => 12, 'msg' => '长度必须为12位']],
     *       ],
     * ]
     * 即：
     *      如果仅提供了字符串型（key是整型），则认为 arg 和 msg 都是空
     *      如果提供了整型下标数组，则认为改数组是 arg，msg 为空
     *      完全形式是如上面 length 的定义
     *
     * @see http://www.easyswoole.com/Manual/3.x/Cn/_book/Components/validate.html
     * @return array
     */
    protected function validateRules(): array
    {
        return [];
    }

    public function index()
    {
        // do nothing
    }

    /**
     * 请求执行前
     * @param null|string $action
     * @return bool|null
     * @throws \Exception
     */
    protected function onRequest(?string $action): ?bool
    {
        $this->formatParams();
        $this->response()->withHeader('Content-type','text/html;charset=UTF-8');

        if (!$this->execMiddlewares('before', $this->request(), $this->response())) {
            return false;
        }

        return parent::onRequest($action);
    }

    /**
     * 请求执行后
     * @param null|string $action
     * @throws \Throwable
     */
    protected function afterAction(?string $action): void
    {
        // 记录 session
        try {
            $this->storeSession();
        } catch (\Throwable $e) {
            $this->onException($e);
        }

        if ($this->responseData) {
            $this->response()->write(
                is_array($this->responseData)
                    ? json_encode($this->responseData, JSON_UNESCAPED_UNICODE)
                    : (string)$this->responseData
            );
        }

        $this->execMiddlewares('after', $this->request(), $this->response());

        parent::afterAction($action);
    }

    protected function gc()
    {
        $this->execMiddlewares('gc');
        $this->responseData = null;

        parent::gc();
    }

    /**
     * 发生异常：记录错误日志，返回错误
     * @param \Throwable $throwable
     * @throws \Throwable
     */
    protected function onException(\Throwable $throwable): void
    {
        $logger = Container::get(LoggerInterface::class);
        $displayMsg = $message = $throwable->getMessage();
        $context = ['trace' => $throwable->getTraceAsString()];
        $retry = 0;
        $data = [];

        if ($throwable instanceof Exception) {
            $context = array_merge($context, $throwable->getContext());
            $retry = (int)$throwable->isShouldRetry();

            if (($data = $throwable->getData()) && !isset($context['data'])) {
                $context['data'] = $data;
            }
        }

        if ($throwable instanceof DBException) {
            // 数据库错误，需要隐藏详情
            $errFlag = mt_rand(10000, 1000000) . mt_rand(10000, 10000000);
            $displayMsg = "数据库错误，错误标识：{$errFlag}";
            $context['db_err_flag'] = $errFlag;
            $logger->critical($message, $context);
        } elseif ($throwable instanceof CriticalErrorException) {
            $logger->critical($message, $context);
        } elseif ($throwable instanceof EmergencyErrorException) {
            $logger->emergency($message, $context);
        } elseif (!$throwable instanceof ValidateException) {
            // 参数校验错误不记录日志
            $logger->error($message, $context);
        }

        $this->return($data, $throwable->getCode() ?: ErrCode::ERROR, $displayMsg, $retry, true);
    }

    protected function formatParams()
    {
        $contentType = $this->request()->getHeader('content-type')[0] ?? "form-data";
        $extractData = false;
        switch ($contentType) {
            case 'application/json':
                // json 格式直接读取 raw 流并转成数组
                $params = json_decode($this->getRawBody(), true) ?: [];
                break;
            case "application/xml":
                // xml 格式直接读取 raw 流并转成数组
                $xmlObj = simplexml_load_string($this->getRawBody());
                $bodyArr = [];
                if ($xmlObj !== false) {
                    $bodyArr = json_decode(json_encode($xmlObj), true) ?: [];
                }

                $params = $bodyArr;
                break;
            default:
                if ($this->request()->getMethod() == "POST") {
                    $extractData = true;
                }
                break;
        }

        $params = array_merge($params ?? [], $this->request()->getRequestParam());
        // 很多内部系统用 data 做了一层封装，自动解包
        if ($extractData && isset($params['data'])) {
            $params = is_string($params['data']) ? json_decode($params['data'], true) : $params['data'];
        }

        $this->requestParams = $params;
    }

    /**
     * 获取原始 body 数据
     */
    public function getRawBody(): string
    {
        $stream = $this->request()->getBody();
        $stream->rewind();
        $body = $stream->getContents();
        $stream->rewind();

        return $body;
    }

    /**
     * 获取请求参数
     * @param null $key
     * @return array|mixed
     */
    protected function params($key = null)
    {
        return isset($key) ? ($this->requestParams[$key] ?? null) : $this->requestParams;
    }

    /**
     * 以 json 格式返回数据
     * @param array $data
     * @param int $status
     * @param string $msg
     * @param int $retry 告诉客户端是否需要重试
     * @param bool $force
     * @return bool
     */
    protected function return($data = [], int $status = 200, string $msg = '', int $retry = 0, bool $force = false): bool
    {
        // 只能调用一次
        if ($this->responseData && !$force) {
            return false;
        }

        $this->responseData = ['status' => $status, 'msg' => $msg, 'info' => $msg, 'data' => $data ?? [], 'retry' => $retry];
        return true;
    }

    /**
     * 重写 validate 方法：验证处理后的数据（因为请求端可能是把请求数据放在 data 里面）
     * @param Validate $validate
     * @return bool
     */
    protected function validate(Validate $validate)
    {
        return $validate->validate($this->params());
    }

    /**
     * 获取或者设置 session
     * 由于 session 是存在 jwt 中，不要存太多东西到 session 中
     * 禁止获取/设置 jwt 关键字，防止造成意料之外的错误
     *
     * 获取 session：
     *      $this->session(): 获取所有 session 值，返回 session 数组
     *      $this->session($key): 获取 session[$key]
     * 设置 session
     *      $this->session($key, $vals): 设置 $vals 数组里面的值到 session 中
     * @param string|array $keyOrVals
     * @param null $val
     * @return array|mixed|null|void
     * @throws \Exception
     */
    protected function session($keyOrVals = '', $val = null)
    {
        // 批量设置 session
        if (is_array($keyOrVals)) {
            $this->innerUpdateSession($keyOrVals);
            return;
        }

        if ($val === null) {
            // 获取 session
            return $this->getSession($keyOrVals);
        }

        // 设置 session
        $this->innerUpdateSession([$keyOrVals => $val]);
    }

    /**
     * @param array $arr
     * @throws \Exception
     */
    private function innerUpdateSession(array $arr)
    {
        if (!$arr) {
            $this->requestParams['__session__'] = [];
            return;
        }

        $session = $this->requestParams['__session__'] ?? [];
        foreach ($arr as $k => $v) {
            if (in_array($k, ['iss', 'exp', 'sub', 'aud', 'nbf', 'iat', 'jti'])) {
                throw new \Exception("禁止获取/设置 jwt 关键字:{$k}", ErrCode::INVALID_ACCESS);
            }

            $session[$k] = $v;
        }

        $this->requestParams['__session__'] = $session;
    }

    private function getSession(string $key)
    {
        $session = $this->requestParams['__session__'] ?? [];
        // 剔除掉 __ 开头的特殊字段
        $session = array_filter(
            $session,
            function ($key) {
                return strpos($key, '__') !== 0;
            },
            ARRAY_FILTER_USE_KEY
        );
        if ($key === '') {
            return $session;
        }

        return $session[$key] ?? null;
    }

    /**
     * 删除某个 session 值
     * @param string $key
     * @throws \Exception
     */
    protected function deleteSession(string $key)
    {
        if (in_array($key, ['iss', 'exp', 'sub', 'aud', 'nbf', 'iat', 'jti'])) {
            throw new \Exception("禁止删除 jwt 关键字:{$key}", ErrCode::INVALID_ACCESS);
        }

        $session = $this->requestParams['__session__'] ?? [];
        if (isset($session[$key])) {
            unset($session[$key]);
            $this->requestParams['__session__'] = $session;
        }
    }

    /**
     * 集成 sso，基于公司的 sso 系统登录
     * @param string $ssoCode
     * @return array 返回用户基本信息
     * @throws \Exception
     */
    protected function ssoLogin(string $ssoCode): array
    {
        if (!$ssoCode) {
            throw new \Exception("sso code required", ErrCode::AUTH_FAIL);
        }

        $conf = Config::getInstance();
        $ssoUrl = $conf->getConf('sso_login_url');
        if (!$ssoUrl) {
            throw new \Exception("sso login url required", ErrCode::AUTH_FAIL);
        }

        // 请求 sso 换取 ticket
        $response = API::retrySimpleInvoke($ssoUrl, 'GET', ['type' => 'code', 'code' => $ssoCode]);
        if (!$response->isBusinessOk() || !$response->getBody('data')) {
            throw new \Exception("sso login fail:" . $response->getBusinessError(), ErrCode::AUTH_FAIL);
        }

        $data = $response->getBody('data');
        $loginer = [
            'uid' => $data['loginer']['id'],
            'account' => $data['loginer']['username'],
            'name' => $data['loginer']['name'],
            'phone' => $data['loginer']['phone'],
        ];
        $ticket = $data['ticket'];
        $expire = $data['expire'];

        // 生成 session
        $this->session($loginer);
        $this->session('__ticket', $ticket);
        $this->requestParams['__session__']['exp'] = $expire;

        return $loginer;
    }

    /**
     * 集成 sso 退出登录
     * @throws \Exception
     */
    protected function ssoLogout()
    {
        if (!$session = $this->requestParams['__session__']) {
            return;
        }

        $ticket = $session['__ticket'] ?? '';
        $ssoLogoutUrl = Config::getInstance()->getConf('sso_logout_url');

        if (!$ticket) {
            return;
        }

        if (!$ssoLogoutUrl) {
            throw new \Exception("sso logout url required", ErrCode::PARAM_VALIDATE_FAIL);
        }

        // 删除 sso 的会话
        $response = API::retrySimpleInvoke($ssoLogoutUrl, "POST", ['ticket' => $ticket]);
        if (!$response->isBusinessOk()) {
            throw new \Exception("sso退出登录失败:" . $response->getBusinessError(), ErrCode::API_INVOKE_FAIL);
        }

        // 删除本地会话
        $this->destroySession();
    }

    /**
     * 销毁会话上下文（退出登录）
     */
    protected function destroySession()
    {
        $this->requestParams['__session__'] = null;
    }

    /**
     * 将 session 保存到 jwt 响应头
     * @throws \Exception
     */
    private function storeSession()
    {
        if (!$session = $this->requestParams['__session__'] ?? null) {
            $this->response()->withHeader("Auth-Token", "");
            return;
        }

        $this->response()->withHeader("Auth-Token", $this->buildJWTToken($session));
    }

    /**
     * @param array $data
     * @return string
     * @throws \Exception
     */
    private function buildJWTToken(array $data): string
    {
        // 从配置中心获取配置信息
        $conf = Config::getInstance();
        $signKey = $conf->getConf('jwt_sign_key');

        if (!$signKey) {
            throw new \Exception("build token fail:jwt sign key required", ErrCode::PARAM_VALIDATE_FAIL);
        }

        $expire = isset($data['__exp']) ? $data['__exp'] : $conf->getConf('jwt_expire');

        // 剔除掉 jwt 关键字
        $data = array_filter($data, function ($key) {
            return !in_array($key, ['iss', 'exp', 'sub', 'aud', 'nbf', 'iat', 'jti', '__exp']);
        }, ARRAY_FILTER_USE_KEY);

        if (!$data) {
            return '';
        }

        $config = Configuration::forSymmetricSigner(new Sha256(), InMemory::plainText($signKey));
        $now = new DateTimeImmutable();
        $builder = $config->builder()
            ->issuedBy('weicheche.cn')
            ->issuedAt($now)
            ->canOnlyBeUsedAfter($now)
            ->expiresAt($now->modify("+{$expire} second"));

        foreach ($data as $k => $v) {
            $builder->withClaim($k, $v);
        }

        $token = $builder->getToken($config->signer(), $config->signingKey())->toString();

        // 加密
        $secret = intval($conf->getConf('jwt_encrypt_on')) ? $conf->getConf('jwt_secret') : '';
        if ($secret) {
            $token = $this->encryptJWTToken($token, $secret);
        }

        return $token;
    }

    private function encryptJWTToken(string $token, string $secret): string
    {
        if (!$token) {
            return '';
        }

        $token = explode('.', $token);
        if (count($token) != 3) {
            return '';
        }

        $token[1] = Encrypt::enc($token[1], $secret);

        return implode('.', $token);
    }
}
