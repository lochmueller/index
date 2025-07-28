<?php

declare(strict_types=1);

namespace Lochmueller\Indexing\Queue\Handler;

use Lochmueller\Indexing\Indexing\Web\WebIndexing;
use Lochmueller\Indexing\Queue\Message\WebIndexMessage;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class WebIndexHandler
{
    public function __construct(private WebIndexing $webIndexing) {}

    public function __invoke(WebIndexMessage $message): void
    {
        $this->webIndexing->handleMessage($message);
    }
}
