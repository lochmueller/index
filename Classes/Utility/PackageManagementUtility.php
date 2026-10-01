<?php

declare(strict_types=1);

namespace Lochmueller\Index\Utility;

use TYPO3\CMS\ContentBlocks\Definition\ContentType\ContentType;
use TYPO3\CMS\ContentBlocks\Definition\TableDefinitionCollection;
use TYPO3\CMS\ContentBlocks\Loader\LoadedContentBlock;
use TYPO3\CMS\ContentBlocks\Registry\ContentBlockRegistry;
use TYPO3\CMS\Core\Package\PackageManager;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Utility class for accessing services of optional packages (e.g. EXT:content_blocks).
 */
class PackageManagementUtility
{
    protected ?TableDefinitionCollection $tableDefinitionCollection = null;

    protected bool $tableDefinitionCollectionInitialized = false;

    /**
     * @var array<string, LoadedContentBlock>|null
     */
    protected ?array $contentBlockList = null;

    public function __construct(
        protected readonly PackageManager $packageManager,
    ) {}

    public function getContentBlockTableDefinitionCollection(): ?TableDefinitionCollection
    {
        if (!$this->tableDefinitionCollectionInitialized) {
            $this->tableDefinitionCollectionInitialized = true;
            if ($this->packageManager->isPackageActive('content_blocks') && class_exists(TableDefinitionCollection::class)) {
                $this->tableDefinitionCollection = GeneralUtility::makeInstance(TableDefinitionCollection::class);
            }
        }

        return $this->tableDefinitionCollection;
    }

    /**
     * @return array<string, LoadedContentBlock>
     */
    public function getContentBlockList(): array
    {
        if ($this->contentBlockList === null) {
            $this->contentBlockList = [];
            if ($this->packageManager->isPackageActive('content_blocks') && class_exists(ContentBlockRegistry::class) && class_exists(ContentType::class)) {
                $registry = GeneralUtility::makeInstance(ContentBlockRegistry::class);
                foreach ($registry->getAll() as $loadedContentBlock) {
                    if ($loadedContentBlock->getContentType() === ContentType::CONTENT_ELEMENT) {
                        $yaml = $loadedContentBlock->getYaml();
                        if (isset($yaml['typeName'])) {
                            $this->contentBlockList[$yaml['typeName']] = $loadedContentBlock;
                        }
                    }
                }
            }
        }

        return $this->contentBlockList;
    }
}
