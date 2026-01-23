<?php

declare(strict_types=1);

namespace Lochmueller\Index\Configuration;

use Lochmueller\Index\Enums\IndexTechnology;
use Lochmueller\Index\Enums\IndexType;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class Configuration
{
    /**
     * @param string[] $fileMounts
     * @param string[] $fileTypes
     * @param array<string, mixed> $configuration
     * @param string[] $partialIndexing
     * @param int[] $languages
     */
    public function __construct(
        public readonly int             $configurationId,
        public int                      $pageId,
        public readonly IndexTechnology $technology,
        public readonly bool            $skipNoSearchPages,
        public readonly bool            $contentIndexing,
        public int                      $levels,
        public readonly array           $fileMounts,
        public readonly array           $fileTypes,
        public readonly array           $configuration,
        public readonly array            $partialIndexing,
        public readonly array            $languages,
        public ?IndexType               $overrideIndexType = null,
    ) {}

    /**
     * @param array<string, mixed> $row
     */
    public static function createByDatabaseRow(array $row): Configuration
    {
        return new self(
            configurationId: (int) $row['uid'],
            pageId: (int) $row['pid'],
            technology: IndexTechnology::from($row['technology']),
            contentIndexing: (bool) $row['content_indexing'],
            skipNoSearchPages: (bool) $row['skip_no_search_pages'],
            levels: (int) $row['levels'],
            fileMounts: GeneralUtility::trimExplode(',', $row['file_mounts'] ?? ''),
            fileTypes: GeneralUtility::trimExplode(',', $row['file_types'] ?? ''),
            configuration: in_array(IndexTechnology::from($row['technology']), [IndexTechnology::Frontend, IndexTechnology::Http]) ? (array) json_decode($row['configuration'], true) : [],
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
