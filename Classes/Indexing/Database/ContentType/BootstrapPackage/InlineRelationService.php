<?php

declare(strict_types=1);

namespace Lochmueller\Index\Indexing\Database\ContentType\BootstrapPackage;

use Lochmueller\Index\Domain\Repository\GenericRepository;
use TYPO3\CMS\Core\Context\LanguageAspect;
use TYPO3\CMS\Core\Domain\Record;
use TYPO3\CMS\Core\Domain\RecordFactory;
use TYPO3\CMS\Core\Domain\Repository\PageRepository;

class InlineRelationService
{
    public function __construct(
        private readonly RecordFactory $recordFactory,
        private readonly GenericRepository $genericRepository,
        private readonly PageRepository $pageRepository,
    ) {}

    /**
     * Find child records by parent content element UID.
     *
     * @return iterable<Record>
     */
    public function findByParent(int $parentUid, string $table, int $languageUid = 0): iterable
    {
        $languages = [0, -1, $languageUid];

        $rows = $this->genericRepository
            ->setTableName($table)
            ->findByParentContentElement($parentUid, $languages);

        foreach ($rows as $row) {
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
