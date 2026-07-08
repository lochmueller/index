<?php

declare(strict_types=1);

namespace Lochmueller\Index\Queue\Message;

final class DeIndexDocumentMessage
{
    public function __construct(
        public int $pageUid,
        public int $languageId
    ) {}

}
