<?php

declare(strict_types=1);

namespace Lochmueller\Index\Domain\Repository;

use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;

abstract class AbstractRepository
{
    public function __construct(
        protected readonly ConnectionPool $connectionPool,
    ) {}

    abstract protected function getTableName(): string;

    /**
     * @param array<string, mixed> $record
     */
    public function insert(array $record): void
    {
        $this->getConnection()->insert($this->getTableName(), $record);
    }

    /**
     * @param array<string, mixed> $record
     * @param array<string, mixed> $identifier
     */
    public function update(array $record, array $identifier): void
    {
        $this->getConnection()->update($this->getTableName(), $record, $identifier);
    }

    protected function getConnection(): Connection
    {
        return $this->connectionPool->getConnectionForTable($this->getTableName());
    }

    public function findByUid(int $uid): ?array
    {
        return BackendUtility::getRecord($this->getTableName(), $uid);
    }
}
