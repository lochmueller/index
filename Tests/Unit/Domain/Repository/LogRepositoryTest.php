<?php

declare(strict_types=1);

namespace Lochmueller\Index\Tests\Unit\Domain\Repository;

use Doctrine\DBAL\Result;
use Lochmueller\Index\Domain\Repository\LogRepository;
use Lochmueller\Index\Tests\Unit\AbstractTest;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\Expression\ExpressionBuilder;
use TYPO3\CMS\Core\Database\Query\QueryBuilder;

class LogRepositoryTest extends AbstractTest
{
    public function testGetTableNameReturnsCorrectTableName(): void
    {
        $connectionPoolStub = $this->createStub(ConnectionPool::class);
        $subject = new LogRepository($connectionPoolStub);

        $reflection = new \ReflectionClass($subject);
        $method = $reflection->getMethod('getTableName');

        self::assertSame('tx_index_domain_model_log', $method->invoke($subject));
    }

    public function testFindByIndexProcessIdReturnsRecordWhenFound(): void
    {
        $indexProcessId = 'test-process-123';
        $expectedRecord = [
            'uid' => 1,
            'index_process_id' => $indexProcessId,
            'start_time' => 1234567890,
        ];

        $result = $this->createStub(Result::class);
        $result->method('fetchAssociative')->willReturn($expectedRecord);

        $expressionBuilder = $this->createStub(ExpressionBuilder::class);
        $expressionBuilder->method('eq')->willReturn('index_process_id = "test-process-123"');
        $expressionBuilder->method('literal')->willReturn('"test-process-123"');

        $queryBuilder = $this->createStub(QueryBuilder::class);
        $queryBuilder->method('select')->willReturnSelf();
        $queryBuilder->method('from')->willReturnSelf();
        $queryBuilder->method('where')->willReturnSelf();
        $queryBuilder->method('setMaxResults')->willReturnSelf();
        $queryBuilder->method('expr')->willReturn($expressionBuilder);
        $queryBuilder->method('executeQuery')->willReturn($result);

        $connection = $this->createStub(Connection::class);
        $connection->method('createQueryBuilder')->willReturn($queryBuilder);

        $connectionPool = $this->createStub(ConnectionPool::class);
        $connectionPool->method('getConnectionForTable')->willReturn($connection);

        $subject = new LogRepository($connectionPool);
        $actualRecord = $subject->findByIndexProcessId($indexProcessId);

        self::assertSame($expectedRecord, $actualRecord);
    }

    public function testFindByIndexProcessIdReturnsNullWhenNotFound(): void
    {
        $indexProcessId = 'non-existent-process';

        $result = $this->createStub(Result::class);
        $result->method('fetchAssociative')->willReturn(false);

        $expressionBuilder = $this->createStub(ExpressionBuilder::class);
        $expressionBuilder->method('eq')->willReturn('index_process_id = "non-existent-process"');
        $expressionBuilder->method('literal')->willReturn('"non-existent-process"');

        $queryBuilder = $this->createStub(QueryBuilder::class);
        $queryBuilder->method('select')->willReturnSelf();
        $queryBuilder->method('from')->willReturnSelf();
        $queryBuilder->method('where')->willReturnSelf();
        $queryBuilder->method('setMaxResults')->willReturnSelf();
        $queryBuilder->method('expr')->willReturn($expressionBuilder);
        $queryBuilder->method('executeQuery')->willReturn($result);

        $connection = $this->createStub(Connection::class);
        $connection->method('createQueryBuilder')->willReturn($queryBuilder);

        $connectionPool = $this->createStub(ConnectionPool::class);
        $connectionPool->method('getConnectionForTable')->willReturn($connection);

        $subject = new LogRepository($connectionPool);
        $actualRecord = $subject->findByIndexProcessId($indexProcessId);

        self::assertNull($actualRecord);
    }

    public function testDeleteOlderThanCallsConnectionDelete(): void
    {
        $timestamp = 1234567890;

        $connection = $this->createMock(Connection::class);
        $connection->expects(self::once())
            ->method('delete')
            ->with(
                'tx_index_domain_model_log',
                ['start_time < ?' => $timestamp],
                [Connection::PARAM_INT],
            );

        $connectionPool = $this->createStub(ConnectionPool::class);
        $connectionPool->method('getConnectionForTable')->willReturn($connection);

        $subject = new LogRepository($connectionPool);
        $subject->deleteOlderThan($timestamp);
    }
}
