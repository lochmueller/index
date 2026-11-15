<?php

declare(strict_types=1);

namespace Lochmueller\Index\Indexing\Field\Value\Transformer;

use Lochmueller\Index\Indexing\Field\Value\FieldValueContext;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[AutoconfigureTag(name: 'index.field_transformer')]
interface FieldTransformerInterface
{
    public function supportsByDefault(FieldValueContext $context): bool;

    public function transform(FieldValueContext $context): mixed;
}
