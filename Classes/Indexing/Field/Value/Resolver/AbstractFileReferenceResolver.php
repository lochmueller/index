<?php

declare(strict_types=1);

namespace Lochmueller\Index\Indexing\Field\Value\Resolver;

use Lochmueller\Index\Indexing\Field\Value\FieldValueContext;
use TYPO3\CMS\Core\Resource\FileReference;

abstract class AbstractFileReferenceResolver implements FieldResolverInterface
{
    public function supportsByDefault(FieldValueContext $context): bool
    {
        return false;
    }

    final public function resolve(FieldValueContext $context): mixed
    {
        if ($context->tcaField->getType() !== 'file') {
            throw new \InvalidArgumentException('The file-reference resolver requires a TCA file field.');
        }
        if ($context->definition['type'] !== $this->getTargetType()) {
            throw new \InvalidArgumentException('The file-reference resolver does not match the SEAL type for "' . $context->targetField . '".');
        }

        $fileReferences = $context->record->get($context->sourceField);
        if ($fileReferences === null) {
            return $context->definition['multiple'] ? [] : null;
        }
        if ($fileReferences instanceof FileReference) {
            $references = [$fileReferences];
        } elseif (is_iterable($fileReferences)) {
            $references = $fileReferences;
        } else {
            throw new \UnexpectedValueException('The file field for "' . $context->targetField . '" did not resolve to a file reference.');
        }

        $values = [];
        foreach ($references as $reference) {
            if (!$reference instanceof FileReference) {
                throw new \UnexpectedValueException('The file field for "' . $context->targetField . '" contains a non-file reference value.');
            }
            $values[] = $this->resolveFileReference($reference);
        }

        if ($context->definition['multiple']) {
            return $values;
        }

        return count($values) > 1 ? $values : ($values[0] ?? null);
    }

    abstract protected function getTargetType(): string;

    abstract protected function resolveFileReference(FileReference $fileReference): mixed;
}
