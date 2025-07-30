<?php

declare(strict_types=1);

namespace Lochmueller\Index\Index\Database\Extender;

interface ExtenderInterface
{
    public function __construct(array $configuration);

    public function getItems(): iterable;
}
