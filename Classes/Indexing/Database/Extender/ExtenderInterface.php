<?php

declare(strict_types=1);

namespace Lochmueller\Indexing\Indexing\Database\Extender;

interface ExtenderInterface
{
    public function __construct(array $configuration);

    public function getItems(): iterable;
}
