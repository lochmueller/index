<?php

declare(strict_types=1);

namespace Lochmueller\Index\Indexing\Field\Value\Resolver;

use Lochmueller\Index\Indexing\Field\Value\FieldValueContext;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[AutoconfigureTag(name: 'index.field_resolver')]
interface FieldResolverInterface
{
    public function supportsByDefault(FieldValueContext $context): bool;

    public function resolve(FieldValueContext $context): mixed;
}
