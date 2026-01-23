<?php

declare(strict_types=1);

namespace Lochmueller\Index\Indexing\Database\ContentType;

use Lochmueller\Index\Indexing\Database\DatabaseIndexingDto;
use TYPO3\CMS\ContentBlocks\Definition\ContentType\ContentType;
use TYPO3\CMS\ContentBlocks\Registry\ContentBlockRegistry;
use TYPO3\CMS\Core\Domain\Record;
use TYPO3\CMS\Core\Package\PackageManager;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class ContentBlockContentType extends SimpleContentType
{
    public function canHandle(Record $record): bool
    {
        $contentBlocks = $this->getContentBlockList();
        return array_key_exists($record->getRecordType(), $contentBlocks);
    }

    public function addContent(Record $record, DatabaseIndexingDto $dto): void
    {
        $contentBlock = $this->getContentBlockList()[$record->getRecordType()];


        #var_dump($contentBlock);
        #var_dump($record->get('uid'));
        #var_dump($record->toArray());
        #var_dump(get_class($record));
        #return;

        // @todo render

        #foreach ($newsRecord->get('content_elements') as $ce) {
        #    $this->contentIndexing->addContent($ce, $dto);
        #}


        #$dto->content .= $return;
    }

    /**
     * @return array<string, mixed>
     */
    protected function getContentBlockList(): array
    {
        static $contentBlockList = null;
        if ($contentBlockList === null) {
            $contentBlockList = [];
            $packageManager = GeneralUtility::makeInstance(PackageManager::class);
            if ($packageManager->isPackageActive('content_blocks') && class_exists(ContentBlockRegistry::class) && class_exists(ContentType::class)) {
                $registry = GeneralUtility::makeInstance(ContentBlockRegistry::class);
                foreach ($registry->getAll() as $loadedContentBlock) {
                    if ($loadedContentBlock->getContentType() === ContentType::CONTENT_ELEMENT) {
                        $yaml = $loadedContentBlock->getYaml();
                        if (isset($yaml['typeName'])) {
                            $contentBlockList[$yaml['typeName']] = $loadedContentBlock;
                        }
                    }
                }
            }
        }
        return $contentBlockList;
    }
}
