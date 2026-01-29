<?php

declare(strict_types=1);

namespace Lochmueller\Index\Tests\Unit\Domain\Repository;

use Doctrine\DBAL\Result;
use Lochmueller\Index\Domain\Repository\ConfigurationRepository;
use Lochmueller\Index\Tests\Unit\AbstractTest;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\QueryBuilder;
use TYPO3\CMS\Core\Database\Query\Restriction\FrontendRestrictionContainer;
use TYPO3\CMS\Core\Database\Query\Restriction\QueryRestrictionContainerInterface;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class ConfigurationRepositoryTest extends AbstractTest
{
    protected bool $resetSingletonInstances = true;

    public function testGetTableNameReturnsCorrectTableName(): void
    {
        $connectionPoolStub = $this->createStub(ConnectionPool::class);
        $subject = new ConfigurationRepository($connectionPoolStub);

        $reflection = new \ReflectionClass($subject);
        $method = $reflection->getMethod('getTableName');

        self::assertSame('tx_index_domain_model_configuration', $method->invoke($subject));
    }

    public function testFindAllReturnsIterableOfRecords(): void
    {
        $expectedRecords = [
            ['uid' => 1, 'title' => 'Config 1'],
            ['uid' => 2, 'title' => 'Config 2'],
        ];

        $result = $this->createStub(Result::class);
        $result->method('iterateAssociative')->willReturn(new \ArrayIterator($expectedRecords));

        $frontendRestrictionContainerStub = $this->createStub(FrontendRestrictionContainer::class);
        GeneralUtility::addInstance(FrontendRestrictionContainer::class, $frontendRestrictionContainerStub);

        $restrictions = $this->createStub(QueryRestrictionContainerInterface::class);
        $restrictions->method('add')->willReturnSelf();

        $queryBuilder = $this->createStub(QueryBuilder::class);
        $queryBuilder->method('getRestrictions')->willReturn($restrictions);
        $queryBuilder->method('select')->willReturnSelf();
        $queryBuilder->method('from')->willReturnSelf();
        $queryBuilder->method('executeQuery')->willReturn($result);

        $connectionPool = $this->createStub(ConnectionPool::class);
        $connectionPool->method('getQueryBuilderForTable')->willReturn($queryBuilder);

        $subject = new ConfigurationRepository($connectionPool);
        $actualRecords = iterator_to_array($subject->findAll());

        self::assertSame($expectedRecords, $actualRecords);
    }

    public function testFindAllAddsFrontendRestrictions(): void
    {
        $result = $this->createStub(Result::class);
        $result->method('iterateAssociative')->willReturn(new \ArrayIterator([]));

        $frontendRestrictionContainerStub = $this->createStub(FrontendRestrictionContainer::class);
        GeneralUtility::addInstance(FrontendRestrictionContainer::class, $frontendRestrictionContainerStub);

        $restrictions = $this->createMock(QueryRestrictionContainerInterface::class);
        $restrictions->expects(self::once())->method('add')->willReturnSelf();

        $queryBuilder = $this->createStub(QueryBuilder::class);
        $queryBuilder->method('getRestrictions')->willReturn($restrictions);
        $queryBuilder->method('select')->willReturnSelf();
        $queryBuilder->method('from')->willReturnSelf();
        $queryBuilder->method('executeQuery')->willReturn($result);

        $connectionPool = $this->createStub(ConnectionPool::class);
        $connectionPool->method('getQueryBuilderForTable')->willReturn($queryBuilder);

        $subject = new ConfigurationRepository($connectionPool);
        iterator_to_array($subject->findAll());
    }

    public function testFindAllReturnsEmptyIterableWhenNoRecords(): void
    {
        $result = $this->createStub(Result::class);
        $result->method('iterateAssociative')->willReturn(new \ArrayIterator([]));

        $frontendRestrictionContainerStub = $this->createStub(FrontendRestrictionContainer::class);
        GeneralUtility::addInstance(FrontendRestrictionContainer::class, $frontendRestrictionContainerStub);

        $restrictions = $this->createStub(QueryRestrictionContainerInterface::class);
        $restrictions->method('add')->willReturnSelf();

        $queryBuilder = $this->createStub(QueryBuilder::class);
        $queryBuilder->method('getRestrictions')->willReturn($restrictions);
        $queryBuilder->method('select')->willReturnSelf();
        $queryBuilder->method('from')->willReturnSelf();
        $queryBuilder->method('executeQuery')->willReturn($result);

        $connectionPool = $this->createStub(ConnectionPool::class);
        $connectionPool->method('getQueryBuilderForTable')->willReturn($queryBuilder);

        $subject = new ConfigurationRepository($connectionPool);
        $actualRecords = iterator_to_array($subject->findAll());

        self::assertSame([], $actualRecords);
    }
}
