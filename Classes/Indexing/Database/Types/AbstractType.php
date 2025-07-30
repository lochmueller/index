<?php

declare(strict_types=1);

namespace Lochmueller\Index\Indexing\Database\Types;

abstract class AbstractType implements TypeInterface
{
    public function __construct(protected array $configuration) {}
}
