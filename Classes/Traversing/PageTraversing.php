<?php

declare(strict_types=1);

namespace Lochmueller\Index\Traversing;

use Lochmueller\Index\Configuration\Configuration;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\QueryBuilder;
use TYPO3\CMS\Core\Database\Query\Restriction\FrontendRestrictionContainer;
use TYPO3\CMS\Core\Site\SiteFinder;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class PageTraversing
{
    public function __construct(
        private SiteFinder $siteFinder,
        #[AutowireIterator('index.extender')]
        protected iterable $extender,
    ) {}

    public function getFrontendInformation(Configuration $configuration): iterable
    {
        $site = $this->siteFinder->getSiteByPageId($configuration->pageId);
        $extenderConfiguration = $configuration->configuration['extender'] ?? [];
        $router = $site->getRouter();

        foreach ($this->getRelevantPageUids($configuration->pageId, $configuration->levels) as $relevantPageUid) {
            foreach ($extenderConfiguration as $item) {
                $dropOriginalUri = isset($item['dropOriginalUri']) && $item['dropOriginalUri'];
                if (!isset($item['limitToPages']) || (isset($item['limitToPages']) && is_array($item['limitToPages']) && in_array($relevantPageUid, $item['limitToPages'], true))) {
                    // Handled via extender
                    foreach ($this->extender as $extender) {
                        if ($extender->getName() === $item['type'] ?? '') {
                            yield from $extender->getItems($configuration, $item, $site, $relevantPageUid);
                            if ($dropOriginalUri) {
                                continue 3;
                            } else {
                                continue 2;
                            }
                        }
                    }
                }
            }

            yield [
                'uri' => $router->generateUri(BackendUtility::getRecord('pages', $relevantPageUid)),
                'pageUid' => $relevantPageUid,
            ];
        }
    }

    protected function getRelevantPageUids(int $id, int $depth): iterable
    {
        yield $id;
        if ($id && $depth > 0) {
            /** @var QueryBuilder $queryBuilder */
            $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable('pages');
            $queryBuilder->getRestrictions()->removeAll();
            $queryBuilder->getRestrictions()->add(GeneralUtility::makeInstance(FrontendRestrictionContainer::class));

            $queryBuilder->select('uid')
                ->from('pages')
                ->where(
                    $queryBuilder->expr()->eq('pid', $queryBuilder->createNamedParameter($id, Connection::PARAM_INT)),
                    $queryBuilder->expr()->eq('sys_language_uid', $queryBuilder->createNamedParameter(0, Connection::PARAM_INT)),
                );

            $statement = $queryBuilder->executeQuery();
            foreach ($statement->iterateAssociative() as $row) {
                yield from $this->getRelevantPageUids($row['uid'], $depth - 1);
            }
        }
    }


}
