<?php

declare(strict_types=1);

namespace Lochmueller\Index\Event\ContentType;

use TYPO3\CMS\Core\Domain\Record;

final class HandleContentTypeEvent
{
    public function __construct(
        public readonly Record $record,
        public readonly bool   $defaultHandled,
        public ?string          $content,
    ) {}
}
