<?php

namespace WecarSwoole\Repository;

use WecarSwoole\MySQLFactory;

/**
 * MySQL 仓储基类
 * 子类必须设置 dbName 属性 （对应数据库配置文件的 key）
 * 不支持一个仓储中跨库查询
 * Class MySQLRepository
 * @package WecarSwoole\Repository
 */
abstract class MySQLRepository extends Repository implements ITransactional
{
    /**
     * @var \Dev\MySQL\Query
     */
    protected $query;
    protected $oldQuery;

    /**
     * MySQLRepository constructor.
     * @throws \Exception
     */
    public function __construct()
    {
        if (!$this->dbAlias()) {
            throw new \Exception('dbName can not be null');
        }

        $this->query = MySQLFactory::build($this->dbAlias());
    }

    public function getContext()
    {
        return $this->query;
    }

    public function setContext($context)
    {
        // 先将原先的 query 暂存
        if (!$this->oldQuery) {
            $this->oldQuery = $this->query;
        }
        $this->query = $context;
    }

    /**
     * 将 context 恢复到原来的值
     */
    public function restoreContext()
    {
        if ($this->oldQuery) {
            $this->query = $this->oldQuery;
            $this->oldQuery = null;
        }
    }

    abstract protected function dbAlias(): string;
}
