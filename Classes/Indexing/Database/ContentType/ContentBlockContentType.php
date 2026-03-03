<?php

declare(strict_types=1);

namespace Lochmueller\Index\Indexing\Database\ContentType;

use Lochmueller\Index\Domain\Repository\GenericRepository;
use Lochmueller\Index\Indexing\Database\DatabaseIndexingDto;
use TYPO3\CMS\ContentBlocks\Definition\ContentType\ContentType;
use TYPO3\CMS\ContentBlocks\Definition\TableDefinitionCollection;
use TYPO3\CMS\ContentBlocks\Definition\TcaFieldDefinition;
use TYPO3\CMS\ContentBlocks\Definition\TcaFieldDefinitionCollection;
use TYPO3\CMS\ContentBlocks\Loader\LoadedContentBlock;
use TYPO3\CMS\ContentBlocks\Registry\ContentBlockRegistry;
use TYPO3\CMS\Core\Context\LanguageAspect;
use TYPO3\CMS\Core\Domain\Record;
use TYPO3\CMS\Core\Domain\RecordFactory;
use TYPO3\CMS\Core\Domain\Repository\PageRepository;
use TYPO3\CMS\Core\Package\PackageManager;
use TYPO3\CMS\Core\Resource\FileReference;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class ContentBlockContentType extends SimpleContentType
{
    private const MAX_INLINE_DEPTH = 10;

    public function __construct(
        protected HeaderContentType $headerContentType,
        private readonly GenericRepository $genericRepository,
        private readonly RecordFactory $recordFactory,
        private readonly PageRepository $pageRepository,
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

        $this->extractFieldsFromColumns($record, $columns, $tableDefinition->tcaFieldDefinitionCollection, $table, $dto, 0);
    }

    /**
     * @param array<string> $columns
     */
    protected function extractFieldsFromColumns(
        Record $record,
        array $columns,
        TcaFieldDefinitionCollection $fieldDefinitionCollection,
        string $table,
        DatabaseIndexingDto $dto,
        int $depth,
    ): void {
        if ($depth > self::MAX_INLINE_DEPTH) {
            return;
        }

        $contentParts = [];
        foreach ($columns as $column) {
            if (in_array($column, ['header', 'subheader', 'header_layout', 'header_position', 'header_link'], true)) {
                continue;
            }

            if (!$fieldDefinitionCollection->hasField($column)) {
                continue;
            }

            $fieldDefinition = $fieldDefinitionCollection->getField($column);
            $fieldType = $fieldDefinition->fieldType;
            $tcaType = $fieldType->getTcaType();

            switch ($tcaType) {
                case 'input':
                case 'text':
                    $value = $this->getFieldValue($record, $column);
                    if ($value !== null && $value !== '') {
                        $contentParts[] = $value;
                    }
                    break;
                case 'file':
                    $fileContent = $this->extractFileContent($record, $column);
                    if ($fileContent !== '') {
                        $contentParts[] = $fileContent;
                    }
                    break;
                case 'inline':
                    $inlineContent = $this->extractInlineContent($record, $fieldDefinition, $dto, $depth);
                    if ($inlineContent !== '') {
                        $contentParts[] = $inlineContent;
                    }
                    break;
            }
        }

        if ($contentParts !== []) {
            $dto->content .= implode(' ', $contentParts);
        }
    }

    protected function extractInlineContent(
        Record $record,
        TcaFieldDefinition $fieldDefinition,
        DatabaseIndexingDto $dto,
        int $depth,
    ): string {
        $tca = $fieldDefinition->getTca();
        $foreignTable = $tca['config']['foreign_table'] ?? '';
        $foreignField = $tca['config']['foreign_field'] ?? '';
        if ($foreignTable === '' || $foreignField === '') {
            return '';
        }

        $tableDefinitionCollection = $this->getTableDefinitionCollection();
        if ($tableDefinitionCollection === null || !$tableDefinitionCollection->hasTable($foreignTable)) {
            return '';
        }

        $childTableDefinition = $tableDefinitionCollection->getTable($foreignTable);
        $languageUid = $record->getLanguageId() ?? 0;

        try {
            $parentUid = (int) $record->get('uid');
        } catch (\Exception) {
            return '';
        }

        $contentParts = [];
        foreach ($this->findChildRecords($parentUid, $foreignTable, $foreignField, $languageUid) as $childRecord) {
            $childColumns = [];
            foreach ($childTableDefinition->tcaFieldDefinitionCollection as $childField) {
                $childColumns[] = $childField->identifier;
            }

            $childDto = clone $dto;
            $childDto->content = '';
            $this->extractFieldsFromColumns(
                $childRecord,
                $childColumns,
                $childTableDefinition->tcaFieldDefinitionCollection,
                $foreignTable,
                $childDto,
                $depth + 1,
            );

            if ($childDto->content !== '') {
                $contentParts[] = $childDto->content;
            }
        }

        return implode(' ', $contentParts);
    }

    /**
     * @return iterable<Record>
     */
    protected function findChildRecords(int $parentUid, string $table, string $foreignField, int $languageUid): iterable
    {
        $languages = [0, -1, $languageUid];

        $rows = $this->genericRepository
            ->setTableName($table)
            ->findByParentField($parentUid, $foreignField, $languages);

        foreach ($rows as $row) {
            try {
                if ($languageUid > 0) {
                    $overlay = $this->pageRepository->getLanguageOverlay(
                        $table,
                        $row,
                        new LanguageAspect($languageUid, $languageUid),
                    );
                    if ($overlay === null) {
                        continue;
                    }
                    $record = $this->recordFactory->createResolvedRecordFromDatabaseRow($table, $overlay);
                    if (!$record instanceof Record) {
                        continue;
                    }
                    $langInfo = $record->getLanguageInfo();
                    if ($langInfo === null || !in_array($langInfo->getLanguageId(), [-1, $languageUid], true)) {
                        continue;
                    }
                    yield $record;
                } else {
                    $record = $this->recordFactory->createResolvedRecordFromDatabaseRow($table, $row);
                    if (!$record instanceof Record) {
                        continue;
                    }
                    yield $record;
                }
            } catch (\Exception) {
                continue;
            }
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
