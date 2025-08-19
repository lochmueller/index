<?php

declare(strict_types=1);

namespace Lochmueller\Index\Configuration;

use Lochmueller\Index\Enums\IndexTechnology;
use Lochmueller\Index\Enums\IndexType;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class Configuration
{
    public function __construct(
        public readonly int             $configurationId,
        public int                      $pageId,
        public readonly IndexTechnology $technology,
        public readonly bool            $skipNoSearchPages,
        public int                      $levels,
        public readonly array           $fileMounts,
        public readonly array           $fileTypes,
        public readonly array           $configuration,
        public readonly array            $partialIndexing,
        public readonly array            $languages,
        public ?IndexType               $overrideIndexType = null,
    ) {}

    public static function createByDatabaseRow(array $row): Configuration
    {
        return new self(
            configurationId: (int) $row['uid'],
            pageId: (int) $row['pid'],
            technology: IndexTechnology::from($row['technology']),
            skipNoSearchPages: (bool) $row['skip_no_search_pages'],
            fileMounts: GeneralUtility::trimExplode(',', $row['file_mounts'] ?? ''),
            fileTypes: GeneralUtility::trimExplode(',', $row['file_types'] ?? ''),
            configuration: IndexTechnology::from($row['technology']) === IndexTechnology::Frontend ? (array) json_decode($row['configuration'], true) : [],
            levels: (int) $row['levels'],
            partialIndexing: GeneralUtility::trimExplode(',', $row['partial_indexing'] ?? '', true),
            languages: GeneralUtility::intExplode(',', $row['languages'] ?? '', true),
        );
    }

    public function modifyForPartialIndexing(int $pageId): Configuration
    {
        $this->overrideIndexType = IndexType::Partial;
        $this->pageId = $pageId;
        $this->levels = 0;

        return $this;
    }

}
