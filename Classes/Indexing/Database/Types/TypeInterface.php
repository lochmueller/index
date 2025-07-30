<?php

declare(strict_types=1);

namespace Lochmueller\Index\Index\Database\Types;

interface TypeInterface
{
    public function __construct(array $configuration);

    public function getItems(): iterable;
}
