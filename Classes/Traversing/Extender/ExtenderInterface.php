<?php

declare(strict_types=1);

namespace Lochmueller\Index\Traversing\Extender;

use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[AutoconfigureTag(name: 'index.extender')]
interface ExtenderInterface
{
    public function __construct(array $configuration);

    public function getItems(): iterable;
}
