<?php

declare(strict_types=1);

namespace Lochmueller\Index\Event;

final class IndexFileEvent
{
    public function __construct(
        /** Meta information */
        public readonly int    $indexConfigurationRecordId,
        /** Content data */
        public string $title,
        public string $content,
        public readonly string $fileIdentifier,
    ) {}
}
