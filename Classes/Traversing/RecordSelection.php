<?php

declare(strict_types=1);

namespace Lochmueller\Index\Traversing;

use Lochmueller\Index\Database\Query\Restriction\NonContainerElementsRestrictionContainer;
use Lochmueller\Index\Domain\Repository\GenericRepository;
use Lochmueller\Index\Utility\PageVisibility;
use TYPO3\CMS\Core\Context\LanguageAspect;
use TYPO3\CMS\Core\Database\Query\Restriction\FrontendRestrictionContainer;
use TYPO3\CMS\Core\Database\Query\Restriction\QueryRestrictionInterface;
use TYPO3\CMS\Core\Domain\Record;
use TYPO3\CMS\Core\Domain\RecordFactory;
use TYPO3\CMS\Core\Domain\Repository\PageRepository;
use TYPO3\CMS\Core\Schema\Capability\LanguageAwareSchemaCapability;
use TYPO3\CMS\Core\Schema\Capability\TcaSchemaCapability;
use TYPO3\CMS\Core\Schema\TcaSchemaFactory;

class RecordSelection
{
    public function __construct(
        private RecordFactory      $recordFactory,
        protected PageRepository   $pageRepository,
        private TcaSchemaFactory   $tcaSchemaFactory,
        private GenericRepository  $genericRepository,
    ) {}

    /**
     * @param int[] $pageUids
     * @param array<class-string<QueryRestrictionInterface>|QueryRestrictionInterface> $restrictions
     * @return iterable<Record>
     */
    public function findRecordsOnPage(
        string $table,
        array $pageUids,
        int $languageUid = 0,
        array $restrictions = [
            FrontendRestrictionContainer::class,
            NonContainerElementsRestrictionContainer::class,
        ],
    ): iterable {
        $schema = $this->tcaSchemaFactory->get($table);
        if (!$schema->isLanguageAware()) {
            $languageField = null;
        } else {
            /** @var LanguageAwareSchemaCapability $languageCapability */
            $languageCapability = $schema->getCapability(TcaSchemaCapability::Language);
            $languageField = $languageCapability->getLanguageField()->getName();
        }
        $languages = array_unique([
            0,
            -1,
            $languageUid,
        ]);

        $rows = $this->genericRepository
            ->setTableName($table)
            ->findRecordsOnPages(
                $pageUids,
                $languageField,
                $languages,
                $restrictions,
            );

        foreach ($rows as $row) {
            if ($languageUid) {
                $overlay = $this->pageRepository->getLanguageOverlay($table, $row, new LanguageAspect($languageUid, $languageUid));
                if ($overlay !== null) {
                    $record = $this->mapRecord($table, $overlay);
                    /** @var \TYPO3\CMS\Core\Domain\Record\LanguageInfo $langInfo */
                    $langInfo = $record->getLanguageInfo();
                    if (in_array($langInfo->getLanguageId(), [-1, $languageUid])) {
                        yield $this->mapRecord($table, $overlay);
                    }
                }
            } else {
                yield $this->mapRecord($table, $row);
            }
        }
    }

    /**
     * @param array<string, mixed> $row
     */
    public function mapRecord(string $table, array $row): Record
    {
        /** @var Record $record */
        $record = $this->recordFactory->createResolvedRecordFromDatabaseRow($table, $row);
        return $record;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findRenderablePage(int $pageUid, int $language = 0): ?array
    {
        $schema = $this->tcaSchemaFactory->get('pages');
        /** @var LanguageAwareSchemaCapability $languageCapability */
        $languageCapability = $schema->getCapability(TcaSchemaCapability::Language);

        $row = $this->genericRepository->setTableName('pages')->findByUid($pageUid);
        if ($row === null) {
            return null;
        }

        if ($this->isExcludedDoktype($row) || !PageVisibility::isVisible($row, time())) {
            return null;
        }

        if ($language !== 0) {
            $languageAspect = new LanguageAspect($language, $language);
            $row = $this->pageRepository->getPageOverlay($row, $languageAspect);
            if ($row[$languageCapability->getLanguageField()->getName()] !== $language) {
                return null;
            }
            if ($this->pageRepository->checkIfPageIsHidden($pageUid, $languageAspect) || !PageVisibility::isVisible($row, time())) {
                return null;
            }
        }
        return $row;
    }

    /**
     * @param array<string, mixed> $row
     */
    protected function isExcludedDoktype(array $row): bool
    {
        return PageVisibility::isExcludedDoktype($row);
    }

}
