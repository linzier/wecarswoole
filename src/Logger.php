<?php

namespace WecarSwoole;

use EasySwoole\Component\Context\ContextManager;
use EasySwoole\Component\Singleton;
use EasySwoole\EasySwoole\ServerManager;
use WecarSwoole\LogHandler\SmSHandler;
use WecarSwoole\LogHandler\WecarFileHandler;
use WecarSwoole\Tasks\Log;
use EasySwoole\EasySwoole\Config;
use EasySwoole\EasySwoole\Swoole\Task\TaskManager;
use Monolog\Handler\NullHandler;
use Psr\Log\AbstractLogger;
use Monolog\Logger as MonoLogger;
use Monolog\Handler\StreamHandler;
use Monolog\Handler\SwiftMailerHandler;
use WecarSwoole\Util\File;

/**
 * 日志
 * Class Logger
 * @package WecarSwoole
 */
class Logger extends AbstractLogger
{
    use Singleton;

    protected static $levels = [
        'DEBUG' => MonoLogger::DEBUG,
        'INFO' => MonoLogger::INFO,
        'NOTICE' => MonoLogger::NOTICE,
        'WARNING' => MonoLogger::WARNING,
        'ERROR' => MonoLogger::ERROR,
        'CRITICAL' => MonoLogger::CRITICAL,
        'ALERT' => MonoLogger::ALERT,
        'EMERGENCY' => MonoLogger::EMERGENCY,
    ];

    protected const DEFAULT_LOGGER_NAME = '__default';

    protected $loggerName;
    protected static $loggerObj = [];

    protected function __construct(string $loggerName = self::DEFAULT_LOGGER_NAME)
    {
        $this->loggerName = $loggerName;
    }

    /**
     *
     * @param string $loggerName
     * @return Logger
     */
    public static function named(string $loggerName): Logger
    {
        return new self($loggerName);
    }

    public function log($level, $message, array $context = array())
    {
        $server = ServerManager::getInstance()->getSwooleServer();
        $log = new Log(['level' => $level, 'message' => $this->wrapMessage($message), 'context' => $context, 'name' => $this->loggerName]);
        // 如果在工作进程中，则投递异步任务，否则直接执行（task进程不能投递异步任务）
        if (!$server->taskworker) {
            TaskManager::async($log);
        } else {
            $log->__onTaskHook($server->worker_id, $server->worker_id);
        }
    }

    private function wrapMessage(string $message): string
    {
        $requestId = ContextManager::getInstance()->get('wcc-request-id');
        if ($requestId) {
            $message = "[" . (defined('ENVIRON') ? ENVIRON : 'unknown') . "][$requestId]" . $message;
        }

        return $message;
    }

    /**
     * Monolog 工厂方法
     * @param string $loggerName
     * @return MonoLogger
     * @throws \Exception
     */
    public static function getMonoLogger(string $loggerName)
    {
        if (isset(self::$loggerObj[$loggerName])) {
            return self::$loggerObj[$loggerName];
        }

        $minLevel = Config::getInstance()->getConf('log_level') ?? 'error';
        if ($minLevel !== 'off' && !array_key_exists(strtoupper($minLevel), self::$levels)) {
            $minLevel = 'error';
        }

        $logger = new MonoLogger(Config::getInstance()->getConf('app_flag') ?? 'app');

        $handlers = $loggerName == self::DEFAULT_LOGGER_NAME ? self::handlers($minLevel) : self::namedHandlers($minLevel, $loggerName);
        foreach ($handlers as $handler) {
            $logger->pushHandler($handler);
        }

        self::$loggerObj[$loggerName] = $logger;

        return $logger;
    }

    /**
     * @param string $minLevel
     * @param string $name
     * @return array
     * @throws \Exception
     */
    private static function namedHandlers(string $minLevel, string $name): array
    {
        if ($minLevel == 'off') {
            return [new NullHandler(MonoLogger::DEBUG)];
        }

        $handles = [];

        $fileName = File::join(STORAGE_ROOT, "logs/{$name}.log");
        $handles[] = new WecarFileHandler(
            $fileName,
            WecarFileHandler::RT_SIZE,
            Config::getInstance()->getConf('max_log_file_size') ?: WecarFileHandler::DEFAULT_FILE_SIZE,
            self::$levels[strtoupper($minLevel)],
            true,
            null,
            true
        );

        // 如果是命令行调试模式，则增加 StreamHandler
        if (DEBUG_MODEL) {
            $handles[] = new StreamHandler(STDOUT);
        }

        return $handles;
    }

    /**
     * @param $minLevel
     * @return array
     * @throws \Exception
     */
    private static function handlers($minLevel): array
    {
        if ($minLevel == 'off') {
            return [new NullHandler(MonoLogger::DEBUG)];
        }

        if (!($tmpConfig = Config::getInstance()->getConf('logger'))) {
            return [];
        }

        $levelConfig = [];
        foreach ($tmpConfig as $levelName => $conf) {
            $levelName = strtoupper($levelName);
            if (!array_key_exists($levelName, self::$levels)) {
                continue;
            }

            $levelConfig[self::$levels[$levelName]] = $conf;
        }
        unset($tmpConfig);

        // 低级别放前面
        ksort($levelConfig);
        $minLevelNum = self::$levels[strtoupper($minLevel)];

        $handles = [];
        foreach ($levelConfig as $levelNum => $config) {
            if ($levelNum < $minLevelNum) {
                continue;
            }

            $cnt = 0;
            foreach ($config as $handleType => $val) {
                switch ($handleType) {
                    case 'file':
                        $handle = new WecarFileHandler(
                            $val,
                            WecarFileHandler::RT_DATE_SIZE,
                            Config::getInstance()->getConf('max_log_file_size') ?: WecarFileHandler::DEFAULT_FILE_SIZE,
                            $levelNum,
                            true,
                            null,
                            true
                        );
                        break;
                    case 'mailer':
                    case 'email':
                        $handle = self::emailHandler($val, $levelNum);
                        break;
                    case 'sms':
                        $handle = self::smsHandler($val, $levelNum);
                        break;
                }

                if (!$handle) {
                    continue;
                }

                // 第一个设置 buddle = false
                if ($cnt++ == 0) {
                    $handle->setBubble(false);
                }

                $handles[] = $handle;
            }
        }

        // 如果是命令行调试模式，则增加 StreamHandler
        if (DEBUG_MODEL) {
            $handles[] = new StreamHandler(STDOUT);
        }

        return $handles;
    }

    private static function emailHandler(array $config, int $levelNum): ?SwiftMailerHandler
    {
        $mailerConfig = $config['driver'] ? Config::getInstance()->getConf("mailer.{$config['driver']}") : null;
        if (!$mailerConfig || !$config['to']) {
            return null;
        }

        $mailer = Mailer::getSwiftMailer(
            $mailerConfig['host'] ?? '',
            $mailerConfig['username'] ?? '',
            $mailerConfig['password'] ?? '',
            $mailerConfig['port'] ?: 465,
            $mailerConfig['encryption'] ?: 'ssl'
        );

        $messager = new \Swift_Message($config['subject'] ?? "日志邮件告警");
        $messager->setFrom(["{$mailerConfig['username']}" => $config['subject'] ?? "日志邮件告警"])
            ->setTo(array_keys($config['to']));
        $emailHandler = new SwiftMailerHandler($mailer, $messager, $levelNum, false);

        return $emailHandler;
    }

    private static function smsHandler(array $config, int $levelNum): ?SmSHandler
    {
        if (!$config) {
            return null;
        }

        return new SmSHandler(array_keys($config), $levelNum);
    }
}
