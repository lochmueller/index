<?php

declare(strict_types=1);

namespace Lochmueller\Index\Tests\Unit\Database\Query\Restriction;

use Lochmueller\Index\Database\Query\Restriction\NonContainerElementsRestrictionContainer;
use Lochmueller\Index\Tests\Unit\AbstractTest;
use TYPO3\CMS\Core\Database\Query\Expression\CompositeExpression;
use TYPO3\CMS\Core\Database\Query\Expression\ExpressionBuilder;
use TYPO3\CMS\Core\Package\PackageManager;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class NonContainerElementsRestrictionContainerTest extends AbstractTest
{
    protected function tearDown(): void
    {
        GeneralUtility::purgeInstances();
        parent::tearDown();
    }

    public function testBuildExpressionReturnsEmptyCompositeWhenContainerPackageNotActive(): void
    {
        $packageManager = $this->createStub(PackageManager::class);
        $packageManager->method('isPackageActive')
            ->willReturn(false);
        GeneralUtility::setSingletonInstance(PackageManager::class, $packageManager);

        $expressionBuilder = $this->createStub(ExpressionBuilder::class);
        $compositeExpression = CompositeExpression::and();
        $expressionBuilder->method('and')
            ->willReturn($compositeExpression);

        $restriction = new NonContainerElementsRestrictionContainer();
        $result = $restriction->buildExpression(['tt' => 'tt_content'], $expressionBuilder);

        self::assertInstanceOf(CompositeExpression::class, $result);
    }

    public function testBuildExpressionReturnsEmptyCompositeForNonTtContentTable(): void
    {
        $packageManager = $this->createStub(PackageManager::class);
        $packageManager->method('isPackageActive')
            ->willReturn(true);
        GeneralUtility::setSingletonInstance(PackageManager::class, $packageManager);

        $expressionBuilder = $this->createStub(ExpressionBuilder::class);
        $compositeExpression = CompositeExpression::and();
        $expressionBuilder->method('and')
            ->willReturn($compositeExpression);

        $restriction = new NonContainerElementsRestrictionContainer();
        $result = $restriction->buildExpression(['p' => 'pages'], $expressionBuilder);

        self::assertInstanceOf(CompositeExpression::class, $result);
    }

    public function testBuildExpressionAddsConstraintForTtContentWhenContainerActive(): void
    {
        $packageManager = $this->createStub(PackageManager::class);
        $packageManager->method('isPackageActive')
            ->willReturn(true);
        GeneralUtility::setSingletonInstance(PackageManager::class, $packageManager);

        $expressionBuilder = $this->createMock(ExpressionBuilder::class);
        $expressionBuilder->expects(self::once())
            ->method('eq')
            ->with('c.tx_container_parent', 0)
            ->willReturn('c.tx_container_parent = 0');

        $compositeExpression = CompositeExpression::and('c.tx_container_parent = 0');
        $expressionBuilder->method('and')
            ->willReturn($compositeExpression);

        $restriction = new NonContainerElementsRestrictionContainer();
        $result = $restriction->buildExpression(['c' => 'tt_content'], $expressionBuilder);

        self::assertInstanceOf(CompositeExpression::class, $result);
    }

    public function testBuildExpressionHandlesMultipleTables(): void
    {
        $packageManager = $this->createStub(PackageManager::class);
        $packageManager->method('isPackageActive')
            ->willReturn(true);
        GeneralUtility::setSingletonInstance(PackageManager::class, $packageManager);

        $expressionBuilder = $this->createMock(ExpressionBuilder::class);
        $expressionBuilder->expects(self::once())
            ->method('eq')
            ->with('content.tx_container_parent', 0)
            ->willReturn('content.tx_container_parent = 0');

        $compositeExpression = CompositeExpression::and('content.tx_container_parent = 0');
        $expressionBuilder->method('and')
            ->willReturn($compositeExpression);

        $restriction = new NonContainerElementsRestrictionContainer();
        $result = $restriction->buildExpression([
            'p' => 'pages',
            'content' => 'tt_content',
            'f' => 'sys_file',
        ], $expressionBuilder);

        self::assertInstanceOf(CompositeExpression::class, $result);
    }

    public function testBuildExpressionWithEmptyQueriedTables(): void
    {
        $packageManager = $this->createStub(PackageManager::class);
        GeneralUtility::setSingletonInstance(PackageManager::class, $packageManager);

        $expressionBuilder = $this->createStub(ExpressionBuilder::class);
        $compositeExpression = CompositeExpression::and();
        $expressionBuilder->method('and')
            ->willReturn($compositeExpression);

        $restriction = new NonContainerElementsRestrictionContainer();
        $result = $restriction->buildExpression([], $expressionBuilder);

        self::assertInstanceOf(CompositeExpression::class, $result);
    }
}
