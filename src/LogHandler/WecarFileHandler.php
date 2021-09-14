<?php

namespace WecarSwoole\LogHandler;

use Monolog\Handler\StreamHandler;
use Monolog\Logger;

/**
 * 文件日志
 * 支持三种rotate方式：1.按日期；2.按大小；3.按日期+大小（默认）
 * Class WecarFileHandler
 * @package WecarSwoole\LogHandler
 */
class WecarFileHandler extends StreamHandler
{
    public const RT_DATE = 1;
    public const RT_SIZE = 2;
    public const RT_DATE_SIZE = 3;

    public const DEFAULT_FILE_SIZE = 400 * 1024 * 1024;//400M

    protected $rotateType;
    protected $maxFileSize;
    protected $filename;
    protected $mustRotate;
    protected $nextRotation;
    protected $filenameFormat;
    protected $dateFormat;

    /**
     * WecarFileHandler constructor.
     * @param $filename string 文件名
     * @param int $rotateType 日志切割方式
     * @param float|int $maxFileSize 当需要按照大小切割时，文件切割的大小
     * @param int $level 最低记录等级
     * @param bool $bubble
     * @param null $filePermission
     * @param bool $useLocking
     * @throws \Exception
     */
    public function __construct(
        $filename,
        $rotateType = self::RT_DATE_SIZE,
        $maxFileSize = self::DEFAULT_FILE_SIZE,
        $level = Logger::DEBUG,
        $bubble = true,
        $filePermission = null,
        $useLocking = false
    ) {
        $this->filename = $filename;
        $this->rotateType = $rotateType;
        $this->maxFileSize = $maxFileSize;
        $this->nextRotation = new \DateTime('tomorrow');
        $this->filenameFormat = $rotateType & self::RT_DATE ? '{filename}-{date}' : '{filename}';
        $this->dateFormat = 'Y-m-d';

        parent::__construct($this->getFilename(), $level, $bubble, $filePermission, $useLocking);
    }

    /**
     * @param array $record
     */
    protected function write(array $record)
    {
        // 如果文件大小超过限制，则rotate
        $type = 0;
        if ($this->willRotateSize()) {
            $type |= self::RT_SIZE;
        }

        if ($this->nextRotation <= $record['datetime']) {
            $type |= self::RT_DATE;
        }

        if ($type) {
            $this->rotate($type);
        }

        parent::write($record);
    }

    /**
     * @param int $type rotate方式：1-日期；2-文件大小；3-日期+文件大小
     */
    protected function rotate(int $type)
    {
        // 先关闭文件流
        $this->close();

        // 文件大小
        if ($type & self::RT_SIZE) {
            $this->rotateFileSize();
        }

        // 日期
        if ($type & self::RT_DATE) {
            $this->rotateDate();
        }
    }

    protected function rotateFileSize()
    {
        // 将当前文件改名
        // 先忽略掉错误
        set_error_handler(function ($errno, $errstr, $errfile, $errline) {});
        rename($this->url, $this->url . "." . date('YmdHis'));
        clearstatcache();
        restore_error_handler();
    }

    protected function rotateDate()
    {
        $this->url = $this->getFilename();
        $this->nextRotation = new \DateTime('tomorrow');
    }

    protected function willRotateSize(): bool
    {
        return $this->rotateType & self::RT_SIZE && $this->maxFileSize > 0 && file_exists($this->url) && filesize($this->url) >= $this->maxFileSize;
    }

    protected function getFilename()
    {
        $fileInfo = pathinfo($this->filename);
        $timedFilename = str_replace(
            array('{filename}', '{date}'),
            array($fileInfo['filename'], date($this->dateFormat)),
            $fileInfo['dirname'] . '/' . $this->filenameFormat
        );

        if (!empty($fileInfo['extension'])) {
            $timedFilename .= '.'.$fileInfo['extension'];
        }

        return $timedFilename;
    }
}
