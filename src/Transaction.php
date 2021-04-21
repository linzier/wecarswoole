<?php

namespace WecarSwoole;

use WecarSwoole\Repository\ITransactional;

/**
 * 事务管理器
 * Class Transaction
 * @package WecarSwoole
 */
class Transaction
{
    private const STATUS_OPEN = 1;
    private const STATUS_CLOSED = 2;

    private $status;
    private $context;
    private $repositories;

    protected function __construct()
    {
        $this->repositories = [];
        $this->status = self::STATUS_OPEN;
    }

    public function __destruct()
    {
        if ($this->status == self::STATUS_OPEN) {
            $this->context->rollback();
        }
    }

    /**
     * 开启事务
     * @param array ...$repositories
     * @return Transaction
     */
    public static function begin(...$repositories): Transaction
    {
        $trans = new static();
        $trans->add(...$repositories);

        return $trans;
    }

    public function commit()
    {
        if ($this->status != self::STATUS_OPEN) {
            throw new \Exception("事务提交失败：事务未开启", ErrCode::ERROR);
        }

        $result = $this->context->commit();
        $this->finish();

        return $result;
    }

    public function rollback()
    {
        if ($this->status != self::STATUS_OPEN) {
            throw new \Exception("事务回滚失败：事务未开启", ErrCode::ERROR);
        }

        $result = $this->context->rollback();
        $this->finish();

        return $result;
    }

    /**
     * 添加仓储到事务中
     * @param array ...$repositories
     */
    public function add(...$repositories): void
    {
        foreach ($repositories as $repository) {
            if (!$repository instanceof ITransactional) {
                continue;
            }

            // 先试图提交之前的事务
            $repository->getContext()->commit();

            // 取第一个仓储的 dbContext
            if (!$this->context) {
                $this->context = $repository->getContext();
                $this->context->begin();
                continue;
            }

            $repository->setContext($this->context);
        }

        $this->repositories = array_merge($this->repositories, $repositories);
    }

    protected function finish()
    {
        $this->status = self::STATUS_CLOSED;
        // 将仓储的 Context 复原
        foreach ($this->repositories as $repository) {
            $repository->restoreContext();
        }
        unset($this->repositories);
        unset($this->context);
    }
}
