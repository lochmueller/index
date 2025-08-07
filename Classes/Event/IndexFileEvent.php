<?php

declare(strict_types=1);

namespace Lochmueller\Index\Event;

final class IndexFileEvent
{
    public function __construct(
        /** Meta information */
        public int    $indexConfigurationRecordId,
        /** Content data */
        public string $title,
        public string $content,
        public string $fileIdentifier,
    ) {}
}
