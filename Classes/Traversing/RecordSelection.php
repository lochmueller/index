<?php

declare(strict_types=1);

namespace Lochmueller\Index\Traversing;

use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Context\Context;
use TYPO3\CMS\Core\Context\LanguageAspect;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\Restriction\FrontendRestrictionContainer;
use TYPO3\CMS\Core\Domain\Record;
use TYPO3\CMS\Core\Domain\RecordFactory;
use TYPO3\CMS\Core\Domain\Repository\PageRepository;
use TYPO3\CMS\Core\Schema\Capability\LanguageAwareSchemaCapability;
use TYPO3\CMS\Core\Schema\Capability\TcaSchemaCapability;
use TYPO3\CMS\Core\Schema\TcaSchemaFactory;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class RecordSelection
{
    public function __construct(
        private RecordFactory    $recordFactory,
        protected PageRepository $pageRepository,
        private TcaSchemaFactory $tcaSchemaFactory,
    ) {}

    /**
     * @return Record[]
     */
    public function findRecordsOnPage(string $table, array $pageUids, int $languageUid = 0): iterable
    {
        $schema = $this->tcaSchemaFactory->get($table);
        /** @var LanguageAwareSchemaCapability $languageCapability */
        $languageCapability = $schema->getCapability(TcaSchemaCapability::Language);

        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable($table);
        $queryBuilder->getRestrictions()->removeAll();
        $queryBuilder->getRestrictions()->add(GeneralUtility::makeInstance(FrontendRestrictionContainer::class));

        // No empty storages
        $pageUids[] = -99;

        $languages = [
            0,
            -1,
            $languageUid,
        ];

        $queryBuilder->select('*')
            ->from($table)
            ->where(
                $queryBuilder->expr()->in('pid', $pageUids),
                $queryBuilder->expr()->in($languageCapability->getLanguageField()->getName(), $languages),
            );

        /** @var PageRepository $pageRepository */
        $pageRepository = GeneralUtility::makeInstance(PageRepository::class, GeneralUtility::makeInstance(Context::class));

        foreach ($queryBuilder->executeQuery()->iterateAssociative() as $row) {
            if ($languageUid) {
                $overlay = $pageRepository->getLanguageOverlay('tx_myext_domain_model_foo', $row, new LanguageAspect($languageUid, $languageUid));
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

    public function mapRecord(string $table, array $row): Record
    {
        /** @var Record $record */
        $record = $this->recordFactory->createResolvedRecordFromDatabaseRow($table, $row);
        return $record;
    }

    public function findRenderablePage(int $pageUid, int $language = 0): ?array
    {
        $schema = $this->tcaSchemaFactory->get('pages');
        /** @var LanguageAwareSchemaCapability $languageCapability */
        $languageCapability = $schema->getCapability(TcaSchemaCapability::Language);

        $row = BackendUtility::getRecord('pages', $pageUid);

        if ($this->isExcludedDoktype($row)) {
            return null;
        }

        if ($language !== 0) {
            $languageAspect = new LanguageAspect($language, $language);
            $row = $this->pageRepository->getPageOverlay($row, $languageAspect);
            if ($row[$languageCapability->getLanguageField()->getName()] !== $language) {
                return null;
            }
            if (empty($row) || $this->pageRepository->checkIfPageIsHidden($pageUid, $languageAspect)) {
                return null;
            }
        }
        return $row;
    }

    protected function isExcludedDoktype(array $row): bool
    {
        return isset($row['doktype']) && in_array((int) $row['doktype'], [
            PageRepository::DOKTYPE_SYSFOLDER,
            PageRepository::DOKTYPE_SPACER,
            PageRepository::DOKTYPE_LINK,
            PageRepository::DOKTYPE_BE_USER_SECTION,
            PageRepository::DOKTYPE_SHORTCUT,
            PageRepository::DOKTYPE_MOUNTPOINT,
        ], true);
    }

}
