<?php

namespace WecarSwoole\Config\Apollo;

use Psr\Log\LoggerInterface;
use Swoole\Coroutine;
use WecarSwoole\Config\Config;
use WecarSwoole\Container;

/**
 * Apollo 客户端
 * 注意：此处用的 curl，如果不是在单独的进程调用，则必须将curl 协程化，否则会造成堵塞
 * Class Client
 * @package WecarSwoole\Config\Apollo
 */
class Client
{
    protected $configServer; //apollo服务端地址
    protected $appId; //apollo配置项目的appid
    protected $cluster = 'default';
    protected $clientIp; //绑定IP做灰度发布用
    protected $notifications = [];
    protected $pullTimeout = 6; //获取某个namespace配置的请求超时时间
    protected $intervalTimeout = 70; //每次请求获取apollo配置变更时的超时时间

    /**
     * ApolloClient constructor.
     * @param string $server apollo服务端地址
     * @param string $appId apollo配置项目的appid
     * @param array $namespaces apollo配置项目的namespace
     */
    public function __construct(string $server, $appId, array $namespaces)
    {
        $this->configServer = $server;
        $this->appId = $appId;

        foreach ($namespaces as $namespace) {
            $this->notifications[$namespace] = ['namespaceName' => $namespace, 'notificationId' => -1];
        }
    }

    public function setCluster($cluster)
    {
        $this->cluster = $cluster;
    }

    public function setClientIp($ip)
    {
        $this->clientIp = $ip;
    }

    public function setPullTimeout($pullTimeout)
    {
        $pullTimeout = intval($pullTimeout);
        if ($pullTimeout < 1 || $pullTimeout > 300) {
            return;
        }

        $this->pullTimeout = $pullTimeout;
    }

    public function setIntervalTimeout($intervalTimeout)
    {
        $intervalTimeout = intval($intervalTimeout);
        if ($intervalTimeout < 1 || $intervalTimeout > 300) {
            return;
        }

        $this->intervalTimeout = $intervalTimeout;
    }

    /**
     * 启动配置循环监听
     * @param \Closure $callback 有配置更新时的回调
     */
    public function start(\Closure $callback = null)
    {
        $logger = Container::get(LoggerInterface::class);

        $logger->info("apollo:start apollo monitor");

        do {
            $notifyResults = $this->get($this->getNotifyUrl(), $this->intervalTimeout);

            $logger->info("apollo:get notify info:" . print_r($notifyResults, true));

            if ($notifyResults['http_code'] != 200) {
                Coroutine::sleep(10);
                continue;
            }

            $notifyResults = $notifyResults['response'];
            $changeList = [];
            foreach ($notifyResults as $r) {
                if ($r['notificationId'] != $this->notifications[$r['namespaceName']]['notificationId']) {
                    $changeList[$r['namespaceName']] = $r['notificationId'];
                }
            }

            if (!$changeList) {
                Coroutine::sleep(10);
                continue;
            }

            $logger->info("apollo:get changelist,will pull data.changelist:" . print_r($changeList, true));

            try {
                $pullRst = $this->pullConfigBatch(array_keys($changeList));
            } catch (\Throwable $e) {
                $logger->critical("apollo:pull data fail.reason:" . $e->getMessage());
                Coroutine::sleep(5);
                continue;
            }

            if ($pullRst['reloaded']) {
                // 有配置变动，需要调用回调函数
                $logger->info("apollo:config changed,reload");

                $callback && $callback();
            }

            foreach ($pullRst['list'] as $namespaceName => $result) {
                $result && ($this->notifications[$namespaceName]['notificationId'] = $changeList[$namespaceName]);
            }
            Coroutine::sleep(5);
        } while (true);
    }

    /**
     * 获取多个namespace的配置-无缓存的方式
     * @param array $namespaces
     * @return array
     */
    private function pullConfigBatch(array $namespaces): array
    {
        if (!$namespaces) {
            return ['list' => [], 'reloaded' => false];
        }

        $responseList = [];
        $reloaded = false;

        $responses = $this->requestAll($this->getPullUrls($namespaces), $this->pullTimeout);

        Container::get(LoggerInterface::class)->info("apollo:get data response:" . print_r($responses, true));

        foreach ($namespaces as $namespace) {
            $responseList[$namespace] = true;
        }

        foreach ($responses as $response) {
            if (!$response['response']) {
                continue;
            }

            $namespace = $response['response']['namespaceName'];
            if ($response['http_code'] == 200) {
                $result = $response['response'];

                if (!is_array($result) || !isset($result['configurations'])) {
                    continue;
                }

                $content = '<?php return ' . var_export($result, true) . ';';
                $this->saveToFile(Config::getApolloCacheFileName($namespace), $content);
                $reloaded = true;
            } elseif ($response['http_code'] != 304) {
                $responseList[$namespace] = false;
            }
        }

        return ['list' => $responseList, 'reloaded' => $reloaded];
    }

    private function saveToFile(string $file, string $content)
    {
        try {
            $dir = dirname($file);
            if (!file_exists($dir)) {
                mkdir($dir, 0755, true);
            }

            Container::get(LoggerInterface::class)->info("apollo:save to file:" . $file);

            file_put_contents($file, $content);
        } catch (\Throwable $e) {
            Container::get(LoggerInterface::class)->critical("apollo:save to file fail.file:{$file},reason:" . $e->getMessage());
        }
    }

    private function getReleaseKey($configFile)
    {
        $releaseKey = '';
        if (file_exists($configFile)) {
            $lastConfig = require_once($configFile);
            is_array($lastConfig) && isset($lastConfig['releaseKey']) && $releaseKey = $lastConfig['releaseKey'];
        }
        return $releaseKey;
    }

    private function getPullUrl(string $namespace): string
    {
        $baseApi = rtrim($this->configServer, '/') . '/configs/' . $this->appId . '/' . $this->cluster . '/';
        $api = $baseApi . $namespace;
        $args = [
            'releaseKey' => $this->getReleaseKey(Config::getApolloCacheFileName($namespace)),
        ];

        if ($this->clientIp) {
            $args['ip'] = $this->clientIp;
        }

        $api .= '?' . http_build_query($args);

        return $api;
    }

    private function getPullUrls(array $namespaces): array
    {
        return array_map(function ($namespace) {
            return $this->getPullUrl($namespace);
        }, $namespaces);
    }

    private function getNotifyUrl(): string
    {
        $params = [
            'appId' => $this->appId,
            'cluster' => $this->cluster,
            'notifications' => json_encode(array_values($this->notifications)),
        ];

        return rtrim($this->configServer, '/') . '/notifications/v2?' . http_build_query($params);
    }

    /**
     * @param string $url
     * @return array
     */
    private function get(string $url, int $timeout): array
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_TIMEOUT , $timeout);
        $result = curl_exec($ch);

        if($error_code = curl_errno($ch)) {
            $err = curl_strerror($error_code) . ".详细信息：" . print_r(curl_getinfo($ch), true);
            curl_close($ch);

            Container::get(LoggerInterface::class)->error("apollo:get {$url} fail:" . $err);
            return [
                'http_code' => 500,
                'response' => $err,
            ];
        }

        curl_close($ch);
        
        return [
            'http_code' => $result ? 200 : 304,
            'response' => $result ? json_decode($result, true) : [],
        ];
    }

    private function requestAll(array $urls, int $timeout): array
    {
        if (!$urls) {
            return [];
        }

        $rsts = [];
        foreach ($urls as $url) {
            $rsts[] = $this->get($url, $timeout);
        }

        return $rsts;
    }
}
