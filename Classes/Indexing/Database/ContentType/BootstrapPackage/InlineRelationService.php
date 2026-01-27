<?php

declare(strict_types=1);

namespace Lochmueller\Index\Indexing\Database\ContentType\BootstrapPackage;

use TYPO3\CMS\Core\Context\LanguageAspect;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\Restriction\FrontendRestrictionContainer;
use TYPO3\CMS\Core\Domain\Record;
use TYPO3\CMS\Core\Domain\RecordFactory;
use TYPO3\CMS\Core\Domain\Repository\PageRepository;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class InlineRelationService
{
    public function __construct(
        private readonly RecordFactory $recordFactory,
        private readonly ConnectionPool $connectionPool,
        private readonly PageRepository $pageRepository,
    ) {}

    /**
     * Find child records by parent content element UID.
     *
     * @return iterable<Record>
     */
    public function findByParent(int $parentUid, string $table, int $languageUid = 0): iterable
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable($table);

        $queryBuilder->getRestrictions()
            ->add(GeneralUtility::makeInstance(FrontendRestrictionContainer::class));

        $languages = [0, -1, $languageUid];

        $queryBuilder->select('*')
            ->from($table)
            ->where(
                $queryBuilder->expr()->eq('tt_content', $parentUid),
                $queryBuilder->expr()->in('sys_language_uid', $languages),
            )
            ->orderBy('sorting', 'ASC');

        foreach ($queryBuilder->executeQuery()->iterateAssociative() as $row) {
            if ($languageUid > 0) {
                $overlay = $this->pageRepository->getLanguageOverlay($table, $row, new LanguageAspect($languageUid, $languageUid));
                if ($overlay !== null) {
                    /** @var Record $record */
                    $record = $this->recordFactory->createResolvedRecordFromDatabaseRow($table, $overlay);
                    /** @var \TYPO3\CMS\Core\Domain\Record\LanguageInfo $langInfo */
                    $langInfo = $record->getLanguageInfo();
                    if (in_array($langInfo->getLanguageId(), [-1, $languageUid], true)) {
                        yield $record;
                    }
                }
            } else {
                /** @var Record $record */
                $record = $this->recordFactory->createResolvedRecordFromDatabaseRow($table, $row);
                yield $record;
            }
        }
    }
}
