<?php

declare(strict_types=1);

namespace Lochmueller\Index\Queue\Handler;

use Lochmueller\Index\Indexing\Web\WebIndexing;
use Lochmueller\Index\Queue\Message\WebIndexMessage;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class WebIndexHandler
{
    public function __construct(private WebIndexing $webIndex) {}

    public function __invoke(WebIndexMessage $message): void
    {
        $this->webIndex->handleMessage($message);
    }
}
