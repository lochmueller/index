<?php

declare(strict_types=1);

namespace Lochmueller\Index\Queue\Message;

final readonly class FileMessage
{
    public function __construct(
        /** Meta information */
        public int             $indexConfigurationRecordId,
        /** Content data */
        public string          $fileIdentifier,
    ) {}

}
