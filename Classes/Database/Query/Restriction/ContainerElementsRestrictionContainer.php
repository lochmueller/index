<?php

declare(strict_types=1);

namespace Lochmueller\Index\Database\Query\Restriction;

use TYPO3\CMS\Core\Database\Query\Expression\CompositeExpression;
use TYPO3\CMS\Core\Database\Query\Expression\ExpressionBuilder;
use TYPO3\CMS\Core\Database\Query\Restriction\QueryRestrictionInterface;
use TYPO3\CMS\Core\Package\PackageManager;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class ContainerElementsRestrictionContainer implements QueryRestrictionInterface
{
    public function __construct(protected int $containerParent) {}

    /**
     * @param array<string, string> $queriedTables
     */
    public function buildExpression(array $queriedTables, ExpressionBuilder $expressionBuilder): CompositeExpression
    {
        $constraints = [];
        $packageManager = GeneralUtility::makeInstance(PackageManager::class);
        if ($packageManager->isPackageActive('container')) {
            foreach ($queriedTables as $tableAlias => $tableName) {
                if ($tableName === 'tt_content') {
                    $constraints[] = $expressionBuilder->eq(
                        $tableAlias . '.tx_container_parent',
                        $this->containerParent,
                    );
                }
            }
        }

        return $expressionBuilder->and(...$constraints);
    }
}
