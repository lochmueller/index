<?php

declare(strict_types=1);

namespace Lochmueller\Index\Tests\Unit\Domain\Repository;

use Doctrine\DBAL\Result;
use Lochmueller\Index\Domain\Repository\GenericRepository;
use Lochmueller\Index\Tests\Unit\AbstractTest;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\Expression\ExpressionBuilder;
use TYPO3\CMS\Core\Database\Query\QueryBuilder;
use TYPO3\CMS\Core\Database\Query\Restriction\FrontendRestrictionContainer;
use TYPO3\CMS\Core\Database\Query\Restriction\QueryRestrictionContainerInterface;
use TYPO3\CMS\Core\Database\Query\Restriction\QueryRestrictionInterface;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class GenericRepositoryTest extends AbstractTest
{
    protected bool $resetSingletonInstances = true;

    public function testSetTableNameReturnsSelf(): void
    {
        $connectionPool = $this->createStub(ConnectionPool::class);
        $subject = new GenericRepository($connectionPool);

        $result = $subject->setTableName('test_table');

        self::assertSame($subject, $result);
    }

    public function testGetTableNameReturnsConfiguredTableName(): void
    {
        $connectionPool = $this->createStub(ConnectionPool::class);
        $subject = new GenericRepository($connectionPool);
        $subject->setTableName('my_custom_table');

        $reflection = new \ReflectionClass($subject);
        $method = $reflection->getMethod('getTableName');

        self::assertSame('my_custom_table', $method->invoke($subject));
    }

    public function testGetTableNameReturnsEmptyStringByDefault(): void
    {
        $connectionPool = $this->createStub(ConnectionPool::class);
        $subject = new GenericRepository($connectionPool);

        $reflection = new \ReflectionClass($subject);
        $method = $reflection->getMethod('getTableName');

        self::assertSame('', $method->invoke($subject));
    }

    public function testCreateQueryBuilderUsesConfiguredTableName(): void
    {
        $tableName = 'tx_test_table';
        $queryBuilder = $this->createStub(QueryBuilder::class);

        $connectionPool = $this->createMock(ConnectionPool::class);
        $connectionPool->expects(self::once())
            ->method('getQueryBuilderForTable')
            ->with($tableName)
            ->willReturn($queryBuilder);

        $subject = new GenericRepository($connectionPool);
        $subject->setTableName($tableName);

        $result = $subject->createQueryBuilder();

        self::assertSame($queryBuilder, $result);
    }

    public function testCreateFrontendQueryBuilderAddsFrontendRestrictions(): void
    {
        $frontendRestrictionContainerStub = $this->createStub(FrontendRestrictionContainer::class);
        GeneralUtility::addInstance(FrontendRestrictionContainer::class, $frontendRestrictionContainerStub);

        $restrictions = $this->createMock(QueryRestrictionContainerInterface::class);
        $restrictions->expects(self::once())->method('add')->willReturnSelf();

        $queryBuilder = $this->createStub(QueryBuilder::class);
        $queryBuilder->method('getRestrictions')->willReturn($restrictions);

        $connectionPool = $this->createStub(ConnectionPool::class);
        $connectionPool->method('getQueryBuilderForTable')->willReturn($queryBuilder);

        $subject = new GenericRepository($connectionPool);
        $subject->setTableName('test_table');

        $subject->createFrontendQueryBuilder();
    }

    public function testCreateFrontendQueryBuilderReturnsQueryBuilder(): void
    {
        $frontendRestrictionContainerStub = $this->createStub(FrontendRestrictionContainer::class);
        GeneralUtility::addInstance(FrontendRestrictionContainer::class, $frontendRestrictionContainerStub);

        $restrictions = $this->createStub(QueryRestrictionContainerInterface::class);
        $restrictions->method('add')->willReturnSelf();

        $queryBuilder = $this->createStub(QueryBuilder::class);
        $queryBuilder->method('getRestrictions')->willReturn($restrictions);

        $connectionPool = $this->createStub(ConnectionPool::class);
        $connectionPool->method('getQueryBuilderForTable')->willReturn($queryBuilder);

        $subject = new GenericRepository($connectionPool);
        $subject->setTableName('test_table');

        $result = $subject->createFrontendQueryBuilder();

        self::assertSame($queryBuilder, $result);
    }

    public function testFindByParentContentElementReturnsRecords(): void
    {
        $parentUid = 42;
        $languages = [0, 1];
        $expectedRecords = [
            ['uid' => 1, 'tt_content' => 42, 'sorting' => 10],
            ['uid' => 2, 'tt_content' => 42, 'sorting' => 20],
        ];

        $frontendRestrictionContainerStub = $this->createStub(FrontendRestrictionContainer::class);
        GeneralUtility::addInstance(FrontendRestrictionContainer::class, $frontendRestrictionContainerStub);

        $result = $this->createStub(Result::class);
        $result->method('iterateAssociative')->willReturn(new \ArrayIterator($expectedRecords));

        $expressionBuilder = $this->createStub(ExpressionBuilder::class);
        $expressionBuilder->method('eq')->willReturn('tt_content = 42');
        $expressionBuilder->method('in')->willReturn('sys_language_uid IN (0, 1)');

        $restrictions = $this->createStub(QueryRestrictionContainerInterface::class);
        $restrictions->method('add')->willReturnSelf();

        $queryBuilder = $this->createStub(QueryBuilder::class);
        $queryBuilder->method('getRestrictions')->willReturn($restrictions);
        $queryBuilder->method('select')->willReturnSelf();
        $queryBuilder->method('from')->willReturnSelf();
        $queryBuilder->method('where')->willReturnSelf();
        $queryBuilder->method('orderBy')->willReturnSelf();
        $queryBuilder->method('expr')->willReturn($expressionBuilder);
        $queryBuilder->method('executeQuery')->willReturn($result);

        $connectionPool = $this->createStub(ConnectionPool::class);
        $connectionPool->method('getQueryBuilderForTable')->willReturn($queryBuilder);

        $subject = new GenericRepository($connectionPool);
        $subject->setTableName('tx_test_items');

        $actualRecords = iterator_to_array($subject->findByParentContentElement($parentUid, $languages));

        self::assertSame($expectedRecords, $actualRecords);
    }

    public function testFindByParentContentElementReturnsEmptyIterableWhenNoRecords(): void
    {
        $parentUid = 999;
        $languages = [0];

        $frontendRestrictionContainerStub = $this->createStub(FrontendRestrictionContainer::class);
        GeneralUtility::addInstance(FrontendRestrictionContainer::class, $frontendRestrictionContainerStub);

        $result = $this->createStub(Result::class);
        $result->method('iterateAssociative')->willReturn(new \ArrayIterator([]));

        $expressionBuilder = $this->createStub(ExpressionBuilder::class);
        $expressionBuilder->method('eq')->willReturn('tt_content = 999');
        $expressionBuilder->method('in')->willReturn('sys_language_uid IN (0)');

        $restrictions = $this->createStub(QueryRestrictionContainerInterface::class);
        $restrictions->method('add')->willReturnSelf();

        $queryBuilder = $this->createStub(QueryBuilder::class);
        $queryBuilder->method('getRestrictions')->willReturn($restrictions);
        $queryBuilder->method('select')->willReturnSelf();
        $queryBuilder->method('from')->willReturnSelf();
        $queryBuilder->method('where')->willReturnSelf();
        $queryBuilder->method('orderBy')->willReturnSelf();
        $queryBuilder->method('expr')->willReturn($expressionBuilder);
        $queryBuilder->method('executeQuery')->willReturn($result);

        $connectionPool = $this->createStub(ConnectionPool::class);
        $connectionPool->method('getQueryBuilderForTable')->willReturn($queryBuilder);

        $subject = new GenericRepository($connectionPool);
        $subject->setTableName('tx_test_items');

        $actualRecords = iterator_to_array($subject->findByParentContentElement($parentUid, $languages));

        self::assertSame([], $actualRecords);
    }

    public function testSetTableNameAllowsMethodChaining(): void
    {
        $connectionPool = $this->createStub(ConnectionPool::class);
        $subject = new GenericRepository($connectionPool);

        $result = $subject->setTableName('table1')->setTableName('table2');

        self::assertSame($subject, $result);
    }

    public function testFindRecordsOnPagesReturnsRecords(): void
    {
        $pageUids = [1, 2];
        $languageField = 'sys_language_uid';
        $languages = [0, -1, 1];
        $expectedRecords = [
            ['uid' => 1, 'pid' => 1, 'sys_language_uid' => 0],
            ['uid' => 2, 'pid' => 2, 'sys_language_uid' => 1],
        ];

        $result = $this->createStub(Result::class);
        $result->method('iterateAssociative')->willReturn(new \ArrayIterator($expectedRecords));

        $expressionBuilder = $this->createStub(ExpressionBuilder::class);
        $expressionBuilder->method('in')->willReturn('pid IN (1, 2, -99)');

        $restrictions = $this->createStub(QueryRestrictionContainerInterface::class);
        $restrictions->method('add')->willReturnSelf();

        $queryBuilder = $this->createStub(QueryBuilder::class);
        $queryBuilder->method('getRestrictions')->willReturn($restrictions);
        $queryBuilder->method('select')->willReturnSelf();
        $queryBuilder->method('from')->willReturnSelf();
        $queryBuilder->method('where')->willReturnSelf();
        $queryBuilder->method('expr')->willReturn($expressionBuilder);
        $queryBuilder->method('executeQuery')->willReturn($result);

        $connectionPool = $this->createStub(ConnectionPool::class);
        $connectionPool->method('getQueryBuilderForTable')->willReturn($queryBuilder);

        $subject = new GenericRepository($connectionPool);
        $subject->setTableName('tt_content');

        $actualRecords = iterator_to_array($subject->findRecordsOnPages($pageUids, $languageField, $languages));

        self::assertSame($expectedRecords, $actualRecords);
    }

    public function testFindRecordsOnPagesAddsCustomRestrictions(): void
    {
        $pageUids = [1];
        $languageField = 'sys_language_uid';
        $languages = [0];

        $result = $this->createStub(Result::class);
        $result->method('iterateAssociative')->willReturn(new \ArrayIterator([]));

        $expressionBuilder = $this->createStub(ExpressionBuilder::class);
        $expressionBuilder->method('in')->willReturn('pid IN (1, -99)');

        $restrictions = $this->createMock(QueryRestrictionContainerInterface::class);
        $restrictions->expects(self::exactly(2))->method('add')->willReturnSelf();

        $queryBuilder = $this->createStub(QueryBuilder::class);
        $queryBuilder->method('getRestrictions')->willReturn($restrictions);
        $queryBuilder->method('select')->willReturnSelf();
        $queryBuilder->method('from')->willReturnSelf();
        $queryBuilder->method('where')->willReturnSelf();
        $queryBuilder->method('expr')->willReturn($expressionBuilder);
        $queryBuilder->method('executeQuery')->willReturn($result);

        $connectionPool = $this->createStub(ConnectionPool::class);
        $connectionPool->method('getQueryBuilderForTable')->willReturn($queryBuilder);

        $subject = new GenericRepository($connectionPool);
        $subject->setTableName('tt_content');

        $customRestriction1 = $this->createStub(QueryRestrictionInterface::class);
        $customRestriction2 = $this->createStub(QueryRestrictionInterface::class);

        iterator_to_array($subject->findRecordsOnPages($pageUids, $languageField, $languages, [
            $customRestriction1,
            $customRestriction2,
        ]));
    }

    public function testFindRecordsOnPagesReturnsEmptyIterableWhenNoRecords(): void
    {
        $pageUids = [999];
        $languageField = 'sys_language_uid';
        $languages = [0];

        $result = $this->createStub(Result::class);
        $result->method('iterateAssociative')->willReturn(new \ArrayIterator([]));

        $expressionBuilder = $this->createStub(ExpressionBuilder::class);
        $expressionBuilder->method('in')->willReturn('pid IN (999, -99)');

        $restrictions = $this->createStub(QueryRestrictionContainerInterface::class);
        $restrictions->method('add')->willReturnSelf();

        $queryBuilder = $this->createStub(QueryBuilder::class);
        $queryBuilder->method('getRestrictions')->willReturn($restrictions);
        $queryBuilder->method('select')->willReturnSelf();
        $queryBuilder->method('from')->willReturnSelf();
        $queryBuilder->method('where')->willReturnSelf();
        $queryBuilder->method('expr')->willReturn($expressionBuilder);
        $queryBuilder->method('executeQuery')->willReturn($result);

        $connectionPool = $this->createStub(ConnectionPool::class);
        $connectionPool->method('getQueryBuilderForTable')->willReturn($queryBuilder);

        $subject = new GenericRepository($connectionPool);
        $subject->setTableName('tt_content');

        $actualRecords = iterator_to_array($subject->findRecordsOnPages($pageUids, $languageField, $languages));

        self::assertSame([], $actualRecords);
    }
}
