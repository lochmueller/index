<?php

declare(strict_types=1);

namespace Lochmueller\Index\Event;

final class ModifyContentEvent
{
    public function __construct(
        public string $content,
    ) {}
}
