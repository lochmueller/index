<?php

declare(strict_types=1);

namespace Lochmueller\Index\Indexing\Field\Value\Resolver;

use Lochmueller\Index\Indexing\Field\Value\FieldValueContext;
use Symfony\Component\DependencyInjection\Attribute\AsTaggedItem;
use TYPO3\CMS\Core\Domain\RecordInterface;
use TYPO3\CMS\Core\Schema\Field\RelationalFieldTypeInterface;

#[AsTaggedItem(index: 'related_record')]
final class RelatedRecordFieldResolver implements FieldResolverInterface
{
    public function supportsByDefault(FieldValueContext $context): bool
    {
        return $context->tcaField instanceof RelationalFieldTypeInterface
            && !in_array($context->tcaField->getType(), ['file', 'group'], true)
            && ($context->definition['type'] === 'text' || isset($context->configuration['resolveField']));
    }

    public function resolve(FieldValueContext $context): mixed
    {
        if (
            !$context->tcaField instanceof RelationalFieldTypeInterface
            || $context->tcaField->getType() === 'file'
        ) {
            throw new \InvalidArgumentException('The related-record resolver requires a TCA record relation.');
        }

        $field = $context->configuration['resolveField'] ?? $this->getLabelField($context->record->getMainType());
        if (!is_string($field) || $field === '') {
            throw new \InvalidArgumentException('The "resolveField" option for "' . $context->targetField . '" must be a field name.');
        }

        $relation = $context->record->get($context->sourceField);
        if ($relation === null) {
            return $context->definition['multiple'] ? [] : null;
        }

        if ($relation instanceof RecordInterface) {
            $records = [$relation];
        } elseif (is_iterable($relation)) {
            $records = $relation;
        } else {
            throw new \UnexpectedValueException('The relation for record field "' . $context->targetField . '" did not resolve to records.');
        }

        $values = [];
        foreach ($records as $record) {
            if (!$record instanceof RecordInterface) {
                throw new \UnexpectedValueException('The relation for record field "' . $context->targetField . '" contains a non-record value.');
            }
            if (!$record->has($field)) {
                throw new \UnexpectedValueException(
                    'Related record "' . $record->getMainType() . '" has no field "' . $field
                    . '" configured for record field "' . $context->targetField . '".',
                );
            }

            $values[] = $record->get($field);
        }

        if ($context->definition['multiple']) {
            return $values;
        }

        return count($values) > 1 ? $values : ($values[0] ?? null);
    }

    private function getLabelField(string $table): string
    {
        $labelField = $GLOBALS['TCA'][$table]['ctrl']['label'] ?? null;
        if (!is_string($labelField) || $labelField === '') {
            throw new \UnexpectedValueException(
                'Related table "' . $table . '" has no TCA ctrl.label field. Configure "resolveField".',
            );
        }

        return $labelField;
    }
}
