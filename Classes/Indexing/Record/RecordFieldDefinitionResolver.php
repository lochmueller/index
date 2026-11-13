<?php

declare(strict_types=1);

namespace Lochmueller\Index\Indexing\Record;

use Lochmueller\Index\Indexing\Field\Definition\FieldDefinitionResolver;
use TYPO3\CMS\Core\Schema\TcaSchema;
use TYPO3\CMS\Core\Schema\TcaSchemaFactory;

final readonly class RecordFieldDefinitionResolver
{
    private FieldDefinitionResolver $fieldDefinitionResolver;

    public function __construct(TcaSchemaFactory $tcaSchemaFactory)
    {
        $this->fieldDefinitionResolver = new FieldDefinitionResolver($tcaSchemaFactory);
    }

    /**
     * @param array<string|int, mixed> $fieldConfiguration
     * @return array<string, array{
     *     source: string,
     *     type: string,
     *     multiple: bool,
     *     searchable: bool,
     *     filterable: bool,
     *     sortable: bool,
     *     distinct: bool,
     *     facet: bool,
     *     options: array<string, mixed>
     * }>
     */
    public function resolve(string $table, array $fieldConfiguration, ?TcaSchema $schema = null): array
    {
        return $this->fieldDefinitionResolver->resolve($table, $fieldConfiguration, $schema);
    }

    public function getTcaSchema(string $table): TcaSchema
    {
        return $this->fieldDefinitionResolver->getTcaSchema($table);
    }

    /**
     * @param array<string, mixed> $record
     * @param array<string, array{
     *     source: string,
     *     type: string,
     *     multiple: bool,
     *     searchable: bool,
     *     filterable: bool,
     *     sortable: bool,
     *     distinct: bool,
     *     facet: bool,
     *     options: array<string, mixed>
     * }> $definitions
     * @return array<string, mixed>
     */
    public function extractValues(array $record, array $definitions): array
    {
        return $this->fieldDefinitionResolver->extractValues($record, $definitions);
    }

    /**
     * @param array<string, mixed> $values
     * @param array<string, array{
     *     source: string,
     *     type: string,
     *     multiple: bool,
     *     searchable: bool,
     *     filterable: bool,
     *     sortable: bool,
     *     distinct: bool,
     *     facet: bool,
     *     options: array<string, mixed>
     * }> $definitions
     * @return array<string, mixed>
     */
    public function normalizeValues(array $values, array $definitions): array
    {
        return $this->fieldDefinitionResolver->normalizeValues($values, $definitions);
    }
}
