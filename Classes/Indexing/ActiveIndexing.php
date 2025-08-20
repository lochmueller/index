<?php

declare(strict_types=1);

namespace Lochmueller\Index\Indexing;

use Lochmueller\Index\Configuration\Configuration;
use Lochmueller\Index\Enums\IndexTechnology;
use Lochmueller\Index\Indexing\Database\DatabaseIndexingQueue;
use Lochmueller\Index\Indexing\Frontend\FrontendIndexingQueue;
use Lochmueller\Index\Indexing\Http\HttpIndexingQueue;

readonly class ActiveIndexing
{
    public function __construct(
        private DatabaseIndexingQueue $databaseIndexQueue,
        private FrontendIndexingQueue $frontendIndexQueue,
        private HttpIndexingQueue     $httpIndexingQueue,
    ) {}

    public function fillQueue(Configuration $configuration, bool $skipFiles = false): void
    {
        if ($configuration->technology === IndexTechnology::Database) {
            $this->databaseIndexQueue->fillQueue($configuration, $skipFiles);
        } elseif ($configuration->technology === IndexTechnology::Frontend) {
            $this->frontendIndexQueue->fillQueue($configuration, $skipFiles);
        } elseif ($configuration->technology === IndexTechnology::Http) {
            $this->httpIndexingQueue->fillQueue($configuration, $skipFiles);
        }
    }
}
