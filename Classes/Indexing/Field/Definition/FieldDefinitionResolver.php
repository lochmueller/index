<?php

declare(strict_types=1);

namespace Lochmueller\Index\Indexing\Field\Definition;

use TYPO3\CMS\Core\Schema\Field\FieldTypeInterface;
use TYPO3\CMS\Core\Schema\TcaSchema;
use TYPO3\CMS\Core\Schema\TcaSchemaFactory;

final readonly class FieldDefinitionResolver
{
    public function __construct(
        private TcaSchemaFactory $tcaSchemaFactory,
    ) {}

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
        if (array_is_list($fieldConfiguration) && $fieldConfiguration !== []) {
            throw new \InvalidArgumentException('Fields must be configured as a target-field map.');
        }

        $schema ??= $this->getTcaSchema($table);
        $definitions = [];
        foreach ($fieldConfiguration as $targetField => $configuration) {
            if (!is_string($targetField) || preg_match('/^[A-Za-z]\w*$/D', $targetField) !== 1) {
                throw new \InvalidArgumentException('Invalid SEAL field name: ' . (string) $targetField);
            }

            if (is_string($configuration)) {
                $configuration = ['source' => $configuration];
            }
            if (!is_array($configuration)) {
                throw new \InvalidArgumentException('Configuration for field "' . $targetField . '" must be a source field name or an object.');
            }

            $source = $configuration['source'] ?? $targetField;
            if (!is_string($source) || $source === '' || !$schema->hasField($source)) {
                throw new \InvalidArgumentException('Unknown TCA field "' . (string) $source . '" for table "' . $table . '".');
            }

            $tcaField = $schema->getField($source);
            $tcaConfiguration = $tcaField->getConfiguration();
            $type = $configuration['type'] ?? $this->getTypeFromTca($tcaField, $tcaConfiguration);
            if (!is_string($type) || !in_array($type, ['text', 'integer', 'float', 'boolean', 'datetime'], true)) {
                throw new \InvalidArgumentException('Unsupported SEAL type for field "' . $targetField . '".');
            }

            $options = $configuration['options'] ?? [];
            if (!is_array($options)) {
                throw new \InvalidArgumentException('Options for field "' . $targetField . '" must be an object.');
            }

            $multiple = $configuration['multiple'] ?? (
                (bool) ($tcaConfiguration['multiple'] ?? false)
                || (int) ($tcaConfiguration['maxitems'] ?? 1) > 1
            );
            $searchable = $configuration['searchable'] ?? (
                $type === 'text' && ($targetField === 'title' || $targetField === 'content' || $tcaField->isSearchable())
            );
            $filterable = $configuration['filterable'] ?? false;
            $sortable = $configuration['sortable'] ?? false;
            $distinct = $configuration['distinct'] ?? false;
            $facet = $configuration['facet'] ?? false;

            foreach ([
                'multiple' => $multiple,
                'searchable' => $searchable,
                'filterable' => $filterable,
                'sortable' => $sortable,
                'distinct' => $distinct,
                'facet' => $facet,
            ] as $optionName => $optionValue) {
                if (!is_bool($optionValue)) {
                    throw new \InvalidArgumentException('Option "' . $optionName . '" for field "' . $targetField . '" must be boolean.');
                }
            }
            if ($type !== 'text' && $searchable) {
                throw new \InvalidArgumentException('SEAL only supports searchable text record fields.');
            }
            if (in_array($targetField, ['title', 'content'], true) && ($type !== 'text' || $multiple || !$searchable)) {
                throw new \InvalidArgumentException('The SEAL "' . $targetField . '" field must be searchable single-value text.');
            }

            $definitions[$targetField] = [
                'source' => $source,
                'type' => $type,
                'multiple' => $multiple,
                'searchable' => $searchable,
                'filterable' => $filterable,
                'sortable' => $sortable,
                'distinct' => $distinct,
                'facet' => $facet,
                'options' => $options,
            ];
        }

        return $definitions;
    }

    public function getTcaSchema(string $table): TcaSchema
    {
        return $this->tcaSchemaFactory->get($table);
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
        $values = [];
        foreach ($definitions as $targetField => $definition) {
            if (!array_key_exists($definition['source'], $record)) {
                continue;
            }

            $values[$targetField] = $record[$definition['source']];
        }

        return $values;
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
        $normalized = [];
        foreach ($values as $targetField => $value) {
            if (!isset($definitions[$targetField])) {
                throw new \UnexpectedValueException('Field "' . $targetField . '" has no configured SEAL schema.');
            }
            $definition = $definitions[$targetField];
            $normalized[$targetField] = $this->normalizeValue($value, $definition['type'], $definition['multiple']);
        }

        return $normalized;
    }

    /**
     * @param array<string, mixed> $configuration
     */
    private function getTypeFromTca(FieldTypeInterface $field, array $configuration): string
    {
        return match ($field->getType()) {
            'check' => 'boolean',
            'datetime' => 'datetime',
            'number' => ($configuration['format'] ?? '') === 'integer' ? 'integer' : 'float',
            'input' => $this->getInputType($configuration),
            default => 'text',
        };
    }

    /**
     * @param array<string, mixed> $configuration
     */
    private function getInputType(array $configuration): string
    {
        $eval = $configuration['eval'] ?? '';
        $evaluations = is_string($eval) ? explode(',', $eval) : [];
        if (in_array('int', $evaluations, true)) {
            return 'integer';
        }
        if (array_intersect(['double2', 'double', 'float'], $evaluations) !== []) {
            return 'float';
        }

        return 'text';
    }

    private function normalizeValue(mixed $value, string $type, bool $multiple): mixed
    {
        if ($multiple) {
            if (is_string($value)) {
                $value = array_filter(
                    array_map('trim', explode(',', $value)),
                    static fn(string $item): bool => $item !== '',
                );
            } elseif (!is_array($value)) {
                $value = $value === null ? [] : [$value];
            }

            return array_values(array_map(
                fn(mixed $item): mixed => $this->normalizeScalar($item, $type),
                $value,
            ));
        }

        if (is_array($value)) {
            throw new \UnexpectedValueException('A record field configured as a single value resolved to an array.');
        }

        return $this->normalizeScalar($value, $type);
    }

    private function normalizeScalar(mixed $value, string $type): mixed
    {
        if ($value === null) {
            return null;
        }
        if ($value instanceof \DateTimeInterface) {
            if (!in_array($type, ['text', 'datetime'], true)) {
                throw new \UnexpectedValueException('A date-time object cannot be indexed as ' . $type . '.');
            }
            return $value->format(\DateTimeInterface::ATOM);
        }
        if ($value instanceof \Stringable) {
            $value = (string) $value;
        } elseif (is_object($value)) {
            throw new \UnexpectedValueException('A record field must be converted to a scalar value before indexing.');
        }

        return match ($type) {
            'integer' => is_numeric($value) && (float) $value === (float) (int) $value
                ? (int) $value
                : throw new \UnexpectedValueException('A record value configured as integer is not an integer.'),
            'float' => is_numeric($value) ? (float) $value : throw new \UnexpectedValueException('A record value configured as float is not numeric.'),
            'boolean' => $this->normalizeBoolean($value),
            'datetime' => $this->normalizeDateTime($value),
            default => is_scalar($value) ? (string) $value : throw new \UnexpectedValueException('A text record field must resolve to a scalar value.'),
        };
    }

    private function normalizeBoolean(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }
        if ($value === 1 || $value === '1' || $value === 'true') {
            return true;
        }
        if ($value === 0 || $value === '0' || $value === 'false') {
            return false;
        }

        throw new \UnexpectedValueException('A boolean record value must be true, false, 1 or 0.');
    }

    private function normalizeDateTime(mixed $value): ?string
    {
        if (is_int($value) || (is_string($value) && is_numeric($value))) {
            $timestamp = (int) $value;
            return $timestamp === 0
                ? null
                : (new \DateTimeImmutable('@' . $timestamp))->setTimezone(new \DateTimeZone('UTC'))->format(\DateTimeInterface::ATOM);
        }
        if (is_string($value)) {
            return $value;
        }

        throw new \UnexpectedValueException('A datetime record field must resolve to a timestamp or string.');
    }
}
