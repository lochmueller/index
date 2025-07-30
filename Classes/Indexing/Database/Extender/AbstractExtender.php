<?php

declare(strict_types=1);

namespace Lochmueller\Index\Indexing\Database\Extender;

abstract class AbstractExtender implements ExtenderInterface
{
    public function __construct(protected array $configuration) {}
}
