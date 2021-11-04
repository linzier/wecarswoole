<?php

namespace WecarSwoole\Process;

use EasySwoole\Component\Process\AbstractProcess;
use EasySwoole\EasySwoole\Command\DefaultCommand\Reload;
use EasySwoole\EasySwoole\Core;
use WecarSwoole\Config\Apollo\Client;
use EasySwoole\EasySwoole\Config as EsConfig;
use Swoole\Process;
use WecarSwoole\Util\File;

/**
 * apollo 配置变更监控程序
 * Class ApolloWatcher
 * @package WecarSwoole\Process
 */
class ApolloWatcher extends AbstractProcess
{
    private $flagPrefix;
    private $willWriteFlag = true;

    /**
     * @param $arg
     * @throws \Throwable
     */
    public function run($arg)
    {
        // 该进程中由于存在 while 无限循环，必须在此处捕获 SIGTERM 信号处理，否则无法正常退出
        Process::signal(SIGTERM, function ($signo) {
            $this->clearFlag();
            Process::kill(getmypid(), SIGKILL);
        });
        Process::signal(SIGINT, function ($signo) {
            $this->clearFlag();
            Process::kill(getmypid(), SIGKILL);
        });

        go(function () {
            EsConfig::getInstance()->loadFile(File::join(CONFIG_ROOT, 'apollo.php'), false);
            $apolloConf = EsConfig::getInstance()->getConf('apollo');

            $this->writeFlag();

            (new Client(
                $apolloConf['server'][ENVIRON],
                $apolloConf['app_id'],
                $apolloConf['namespaces']
            ))->start(function () {
                // 重启服务
                (new Reload())->exec(['all', Core::getInstance()->isDev() ? 'dev' : 'produce']);
            });
        });
    }

    public function onShutDown()
    {
        // nothing
    }

    public function onReceive(string $str)
    {
        // nothing
    }

    public function setFlag(bool $willWriteFlag, string $flagPrefix = '')
    {
        $this->willWriteFlag = $willWriteFlag;
        $this->flagPrefix = $flagPrefix;
    }

    private function clearFlag()
    {
        $fileName = $this->getFlagFileName();
        if ($fileName && file_exists($fileName)) {
            unlink($fileName);
        }
    }

    private function writeFlag()
    {
        if (!$fname = $this->getFlagFileName()) {
            return;
        }

        file_put_contents($fname, "pid:" . getmypid() . ",time:" . date('Y-m-d H:i:s'));
    }

    private function getFlagFileName(): string
    {
        $name = $this->flagPrefix ?: $this->getProcessName();
        if (!$this->willWriteFlag || !$name) {
            return '';
        }

        return File::join(STORAGE_ROOT, "temp/{$name}.txt");
    }
}
