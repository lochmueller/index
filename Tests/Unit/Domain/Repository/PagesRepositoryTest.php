<?php

declare(strict_types=1);

namespace Lochmueller\Index\Tests\Unit\Domain\Repository;

use Doctrine\DBAL\Result;
use Lochmueller\Index\Domain\Repository\PagesRepository;
use Lochmueller\Index\Tests\Unit\AbstractTest;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\Expression\ExpressionBuilder;
use TYPO3\CMS\Core\Database\Query\QueryBuilder;
use TYPO3\CMS\Core\Database\Query\Restriction\FrontendRestrictionContainer;
use TYPO3\CMS\Core\Database\Query\Restriction\QueryRestrictionContainerInterface;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class PagesRepositoryTest extends AbstractTest
{
    protected bool $resetSingletonInstances = true;

    public function testClassCanBeInstantiated(): void
    {
        $subject = new PagesRepository(
            $this->createStub(ConnectionPool::class),
        );

        self::assertInstanceOf(PagesRepository::class, $subject);
    }

    public function testGetTableNameReturnsPages(): void
    {
        $connectionPoolStub = $this->createStub(ConnectionPool::class);
        $subject = new PagesRepository($connectionPoolStub);

        $reflection = new \ReflectionClass($subject);
        $method = $reflection->getMethod('getTableName');

        self::assertSame('pages', $method->invoke($subject));
    }

    public function testFindChildPageUidsReturnsChildPageUids(): void
    {
        $parentId = 10;
        $expectedUids = [11, 12, 13];
        $rows = [
            ['uid' => 11],
            ['uid' => 12],
            ['uid' => 13],
        ];

        $frontendRestrictionContainerStub = $this->createStub(FrontendRestrictionContainer::class);
        GeneralUtility::addInstance(FrontendRestrictionContainer::class, $frontendRestrictionContainerStub);

        $result = $this->createStub(Result::class);
        $result->method('iterateAssociative')->willReturn(new \ArrayIterator($rows));

        $expressionBuilder = $this->createStub(ExpressionBuilder::class);
        $expressionBuilder->method('eq')->willReturn('pid = 10');

        $restrictions = $this->createStub(QueryRestrictionContainerInterface::class);
        $restrictions->method('add')->willReturnSelf();

        $queryBuilder = $this->createStub(QueryBuilder::class);
        $queryBuilder->method('getRestrictions')->willReturn($restrictions);
        $queryBuilder->method('select')->willReturnSelf();
        $queryBuilder->method('from')->willReturnSelf();
        $queryBuilder->method('where')->willReturnSelf();
        $queryBuilder->method('expr')->willReturn($expressionBuilder);
        $queryBuilder->method('createNamedParameter')->willReturn('10');
        $queryBuilder->method('executeQuery')->willReturn($result);

        $connectionPool = $this->createStub(ConnectionPool::class);
        $connectionPool->method('getQueryBuilderForTable')->willReturn($queryBuilder);

        $subject = new PagesRepository($connectionPool);
        $actualUids = iterator_to_array($subject->findChildPageUids($parentId));

        self::assertSame($expectedUids, $actualUids);
    }

    public function testFindChildPageUidsReturnsEmptyIterableWhenNoChildren(): void
    {
        $parentId = 999;

        $frontendRestrictionContainerStub = $this->createStub(FrontendRestrictionContainer::class);
        GeneralUtility::addInstance(FrontendRestrictionContainer::class, $frontendRestrictionContainerStub);

        $result = $this->createStub(Result::class);
        $result->method('iterateAssociative')->willReturn(new \ArrayIterator([]));

        $expressionBuilder = $this->createStub(ExpressionBuilder::class);
        $expressionBuilder->method('eq')->willReturn('pid = 999');

        $restrictions = $this->createStub(QueryRestrictionContainerInterface::class);
        $restrictions->method('add')->willReturnSelf();

        $queryBuilder = $this->createStub(QueryBuilder::class);
        $queryBuilder->method('getRestrictions')->willReturn($restrictions);
        $queryBuilder->method('select')->willReturnSelf();
        $queryBuilder->method('from')->willReturnSelf();
        $queryBuilder->method('where')->willReturnSelf();
        $queryBuilder->method('expr')->willReturn($expressionBuilder);
        $queryBuilder->method('createNamedParameter')->willReturn('999');
        $queryBuilder->method('executeQuery')->willReturn($result);

        $connectionPool = $this->createStub(ConnectionPool::class);
        $connectionPool->method('getQueryBuilderForTable')->willReturn($queryBuilder);

        $subject = new PagesRepository($connectionPool);
        $actualUids = iterator_to_array($subject->findChildPageUids($parentId));

        self::assertSame([], $actualUids);
    }

    public function testFindChildPageUidsAddsFrontendRestrictions(): void
    {
        $frontendRestrictionContainerStub = $this->createStub(FrontendRestrictionContainer::class);
        GeneralUtility::addInstance(FrontendRestrictionContainer::class, $frontendRestrictionContainerStub);

        $result = $this->createStub(Result::class);
        $result->method('iterateAssociative')->willReturn(new \ArrayIterator([]));

        $expressionBuilder = $this->createStub(ExpressionBuilder::class);
        $expressionBuilder->method('eq')->willReturn('pid = 1');

        $restrictions = $this->createMock(QueryRestrictionContainerInterface::class);
        $restrictions->expects(self::once())->method('add')->willReturnSelf();

        $queryBuilder = $this->createStub(QueryBuilder::class);
        $queryBuilder->method('getRestrictions')->willReturn($restrictions);
        $queryBuilder->method('select')->willReturnSelf();
        $queryBuilder->method('from')->willReturnSelf();
        $queryBuilder->method('where')->willReturnSelf();
        $queryBuilder->method('expr')->willReturn($expressionBuilder);
        $queryBuilder->method('createNamedParameter')->willReturn('1');
        $queryBuilder->method('executeQuery')->willReturn($result);

        $connectionPool = $this->createStub(ConnectionPool::class);
        $connectionPool->method('getQueryBuilderForTable')->willReturn($queryBuilder);

        $subject = new PagesRepository($connectionPool);
        iterator_to_array($subject->findChildPageUids(1));
    }

    public function testFindChildPageUidsYieldsIntegerValues(): void
    {
        $rows = [
            ['uid' => '100'],
            ['uid' => '200'],
        ];

        $frontendRestrictionContainerStub = $this->createStub(FrontendRestrictionContainer::class);
        GeneralUtility::addInstance(FrontendRestrictionContainer::class, $frontendRestrictionContainerStub);

        $result = $this->createStub(Result::class);
        $result->method('iterateAssociative')->willReturn(new \ArrayIterator($rows));

        $expressionBuilder = $this->createStub(ExpressionBuilder::class);
        $expressionBuilder->method('eq')->willReturn('pid = 1');

        $restrictions = $this->createStub(QueryRestrictionContainerInterface::class);
        $restrictions->method('add')->willReturnSelf();

        $queryBuilder = $this->createStub(QueryBuilder::class);
        $queryBuilder->method('getRestrictions')->willReturn($restrictions);
        $queryBuilder->method('select')->willReturnSelf();
        $queryBuilder->method('from')->willReturnSelf();
        $queryBuilder->method('where')->willReturnSelf();
        $queryBuilder->method('expr')->willReturn($expressionBuilder);
        $queryBuilder->method('createNamedParameter')->willReturn('1');
        $queryBuilder->method('executeQuery')->willReturn($result);

        $connectionPool = $this->createStub(ConnectionPool::class);
        $connectionPool->method('getQueryBuilderForTable')->willReturn($queryBuilder);

        $subject = new PagesRepository($connectionPool);
        $actualUids = iterator_to_array($subject->findChildPageUids(1));

        self::assertSame([100, 200], $actualUids);
        self::assertContainsOnlyInt($actualUids);
    }
}
