<?php

namespace WecarSwoole;

use EasySwoole\EasySwoole\Config;
use WecarSwoole\Crontab\WecarCrontab;
use WecarSwoole\Util\File;

/**
 * 只能在mainServerCreate里面调
 * Class CronTabUtil
 * @package WecarSwoole
 */
class CronTabUtil
{
    private static $createdCron = false;

    /**
     * 注册定时任务，多台服务器只能有一台注册
     * @throws \Exception
     */
    public static function register()
    {
        if (self::$createdCron) {
            return;
        }

        Config::getInstance()->loadFile(File::join(CONFIG_ROOT, 'cron.php'), false);
        $conf = Config::getInstance()->getConf('cron');

        // 兼容旧版（旧版的crontab服务器ip是在cron.php里面配置的，且需要在此处决定是否要在此服务器创建定时任务）
        if (isset($conf['ip']) && !self::willRunCrontabOld($conf)) {
            return;
        }

        self::$createdCron = true;

        $tasks = $conf['tasks'] ?? $conf;// 新旧格式兼容

        if (!$tasks) {
            return;
        }

        // 添加定时任务
        $crontab = WecarCrontab::getInstance(isset($conf['ip']) ? false : true);
        foreach ($tasks as $task) {
            $crontab->addTask($task);
        }

        $crontab->run();
    }

    /**
     * 老版检测，在主进程里面执行
     * @param array $conf
     * @return bool
     */
    public static function willRunCrontabOld(array $conf): bool
    {
        // 优先判断环境变量
        if (self::hasCronEnv()) {
            return true;
        }

        if (!isset($conf['ip']) || !$conf['ip']) {
            return false;
        }

        $ips = is_string($conf['ip']) ? [$conf['ip']] : $conf['ip'];
        $env = defined('ENVIRON') ? ENVIRON : 'produce';
        reset($ips);
        if (!is_int(key($ips))) {
            $ips = [$ips[$env]];
        }

        if (!$ips || !array_intersect($ips, array_values(swoole_get_local_ip()))) {
            return false;
        }
        return true;
    }

    /**
     * 新版检测，在自定义进程里面执行
     * @return bool
     */
    public static function willRunCrontabNew(): bool
    {
        // 优先判断环境变量
        if (self::hasCronEnv()) {
            return true;
        }

        $serverIp = Config::getInstance()->getConf('crontab_server');
        if (!$serverIp || !in_array($serverIp, swoole_get_local_ip())) {
            return false;
        }

        return true;
    }

    /**
     * 是否设置了 crontab 环境变量
     * 在容器模式下，无法通过ip来判断是否需要执行 crontab，我们通过环境变量来判断
     * 此判断优先
     * @return bool
     */
    private static function hasCronEnv(): bool
    {
        $on = getenv('WECARSWOOLE_CRON');
        if ($on && strtolower(trim($on)) == 'on') {
            return true;
        }

        return false;
    }
}
