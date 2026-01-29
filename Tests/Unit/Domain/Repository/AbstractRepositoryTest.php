<?php

declare(strict_types=1);

namespace Lochmueller\Index\Tests\Unit\Domain\Repository;

use Lochmueller\Index\Tests\Unit\AbstractTest;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;

class AbstractRepositoryTest extends AbstractTest
{
    public function testInsertCallsConnectionInsert(): void
    {
        $tableName = 'test_table';
        $record = ['field' => 'value', 'number' => 42];

        $connection = $this->createMock(Connection::class);
        $connection->expects(self::once())
            ->method('insert')
            ->with($tableName, $record);

        $connectionPool = $this->createStub(ConnectionPool::class);
        $connectionPool->method('getConnectionForTable')
            ->with($tableName)
            ->willReturn($connection);

        $subject = new class ($connectionPool, $tableName) extends \Lochmueller\Index\Domain\Repository\AbstractRepository {
            public function __construct(
                \TYPO3\CMS\Core\Database\ConnectionPool $connectionPool,
                private readonly string $tableName,
            ) {
                parent::__construct($connectionPool);
            }

            protected function getTableName(): string
            {
                return $this->tableName;
            }
        };

        $subject->insert($record);
    }

    public function testUpdateCallsConnectionUpdate(): void
    {
        $tableName = 'test_table';
        $record = ['field' => 'updated_value'];
        $identifier = ['uid' => 123];

        $connection = $this->createMock(Connection::class);
        $connection->expects(self::once())
            ->method('update')
            ->with($tableName, $record, $identifier);

        $connectionPool = $this->createStub(ConnectionPool::class);
        $connectionPool->method('getConnectionForTable')
            ->with($tableName)
            ->willReturn($connection);

        $subject = new class ($connectionPool, $tableName) extends \Lochmueller\Index\Domain\Repository\AbstractRepository {
            public function __construct(
                \TYPO3\CMS\Core\Database\ConnectionPool $connectionPool,
                private readonly string $tableName,
            ) {
                parent::__construct($connectionPool);
            }

            protected function getTableName(): string
            {
                return $this->tableName;
            }
        };

        $subject->update($record, $identifier);
    }

    public function testGetConnectionReturnsConnectionForTable(): void
    {
        $tableName = 'test_table';
        $connection = $this->createStub(Connection::class);

        $connectionPool = $this->createMock(ConnectionPool::class);
        $connectionPool->expects(self::once())
            ->method('getConnectionForTable')
            ->with($tableName)
            ->willReturn($connection);

        $subject = new class ($connectionPool, $tableName) extends \Lochmueller\Index\Domain\Repository\AbstractRepository {
            public function __construct(
                \TYPO3\CMS\Core\Database\ConnectionPool $connectionPool,
                private readonly string $tableName,
            ) {
                parent::__construct($connectionPool);
            }

            protected function getTableName(): string
            {
                return $this->tableName;
            }

            public function exposeGetConnection(): Connection
            {
                return $this->getConnection();
            }
        };

        $result = $subject->exposeGetConnection();

        self::assertSame($connection, $result);
    }
}
