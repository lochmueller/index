<?php

declare(strict_types=1);

namespace Lochmueller\Index\Indexing\Database\ContentType;

use Lochmueller\Index\Indexing\Database\DatabaseIndexingDto;
use TYPO3\CMS\ContentBlocks\Definition\ContentType\ContentType;
use TYPO3\CMS\ContentBlocks\Definition\TableDefinitionCollection;
use TYPO3\CMS\ContentBlocks\Loader\LoadedContentBlock;
use TYPO3\CMS\ContentBlocks\Registry\ContentBlockRegistry;
use TYPO3\CMS\Core\Domain\Record;
use TYPO3\CMS\Core\Package\PackageManager;
use TYPO3\CMS\Core\Resource\FileReference;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class ContentBlockContentType extends SimpleContentType
{
    public function __construct(
        protected HeaderContentType $headerContentType,
    ) {}

    public function canHandle(Record $record): bool
    {
        $contentBlocks = $this->getContentBlockList();
        $recordType = $record->getRecordType();
        return $recordType !== null && array_key_exists($recordType, $contentBlocks);
    }

    public function addContent(Record $record, DatabaseIndexingDto $dto): void
    {
        $recordType = $record->getRecordType();
        if ($recordType === null) {
            return;
        }

        $contentBlock = $this->getContentBlockList()[$recordType] ?? null;
        if (!($contentBlock instanceof LoadedContentBlock)) {
            return;
        }

        // Add header content (header, subheader)
        $this->headerContentType->addContent($record, $dto);

        // Extract text content from content block fields
        $this->extractContentBlockFields($record, $contentBlock, $dto);
    }

    protected function extractContentBlockFields(Record $record, LoadedContentBlock $contentBlock, DatabaseIndexingDto $dto): void
    {
        $tableDefinitionCollection = $this->getTableDefinitionCollection();
        if ($tableDefinitionCollection === null) {
            return;
        }

        $table = (string) ContentType::CONTENT_ELEMENT->getTable();
        if (!$tableDefinitionCollection->hasTable($table)) {
            return;
        }

        $tableDefinition = $tableDefinitionCollection->getTable($table);
        $typeName = (string) ($contentBlock->getYaml()['typeName'] ?? '');
        if ($typeName === '' || !$tableDefinition->contentTypeDefinitionCollection->hasType($typeName)) {
            return;
        }

        $contentTypeDefinition = $tableDefinition->contentTypeDefinitionCollection->getType($typeName);
        $columns = $contentTypeDefinition->getColumns();

        $contentParts = [];
        foreach ($columns as $column) {
            // Skip standard fields that are handled elsewhere
            if (in_array($column, ['header', 'subheader', 'header_layout', 'header_position', 'header_link'], true)) {
                continue;
            }

            if (!$tableDefinition->tcaFieldDefinitionCollection->hasField($column)) {
                continue;
            }

            $fieldDefinition = $tableDefinition->tcaFieldDefinitionCollection->getField($column);
            $fieldType = $fieldDefinition->fieldType;
            $tcaType = $fieldType->getTcaType();

            // Extract text-based content
            if (in_array($tcaType, ['input', 'text'], true)) {
                $value = $this->getFieldValue($record, $column);
                if ($value !== null && $value !== '') {
                    $contentParts[] = $value;
                }
            }

            // Extract file references (title, description)
            if ($tcaType === 'file') {
                $fileContent = $this->extractFileContent($record, $column);
                if ($fileContent !== '') {
                    $contentParts[] = $fileContent;
                }
            }
        }

        if ($contentParts !== []) {
            $dto->content .= implode(' ', $contentParts);
        }
    }

    protected function getFieldValue(Record $record, string $column): ?string
    {
        try {
            $value = $record->get($column);
            if (is_string($value)) {
                return trim($value);
            }
            if (is_int($value) || is_float($value)) {
                return (string) $value;
            }
        } catch (\Exception) {
            // Field not accessible
        }
        return null;
    }

    protected function extractFileContent(Record $record, string $column): string
    {
        try {
            $files = $record->get($column);
            if ($files === null) {
                return '';
            }

            $result = [];
            $iterator = is_iterable($files) ? $files : [$files];
            foreach ($iterator as $file) {
                if ($file instanceof FileReference) {
                    $title = $file->getTitle();
                    $description = $file->getDescription();
                    if ($title !== '') {
                        $result[] = $title;
                    }
                    if ($description !== '') {
                        $result[] = $description;
                    }
                }
            }
            return implode(' ', $result);
        } catch (\Exception) {
            return '';
        }
    }

    protected function getTableDefinitionCollection(): ?TableDefinitionCollection
    {
        static $tableDefinitionCollection = null;
        static $initialized = false;

        if (!$initialized) {
            $initialized = true;
            $packageManager = GeneralUtility::makeInstance(PackageManager::class);
            if ($packageManager->isPackageActive('content_blocks') && class_exists(TableDefinitionCollection::class)) {
                $tableDefinitionCollection = GeneralUtility::makeInstance(TableDefinitionCollection::class);
            }
        }

        return $tableDefinitionCollection;
    }

    /**
     * @return array<string, LoadedContentBlock>
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
