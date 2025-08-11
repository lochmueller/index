<?php

declare(strict_types=1);

namespace Lochmueller\Index\Indexing\Frontend;

use Lochmueller\Index\Configuration\Configuration;
use Lochmueller\Index\Enums\IndexTechnology;
use Lochmueller\Index\Enums\IndexType;
use Lochmueller\Index\Indexing\File\FileIndexingQueue;
use Lochmueller\Index\Indexing\IndexingInterface;
use Lochmueller\Index\Queue\Message\FinishProcessMessage;
use Lochmueller\Index\Queue\Message\FrontendIndexMessage;
use Lochmueller\Index\Queue\Message\StartProcessMessage;
use Symfony\Component\Messenger\MessageBusInterface;
use TYPO3\CMS\Core\Site\SiteFinder;

readonly class FrontendIndexingQueue implements IndexingInterface
{
    public function __construct(
        private MessageBusInterface $bus,
        private FileIndexingQueue        $fileIndexing,
        private SiteFinder          $siteFinder,
    ) {}

    public function fillQueue(Configuration $configuration): void
    {
        $site = $this->siteFinder->getSiteByPageId($configuration->pageId);

        $id = uniqid('cache-index', true);
        $this->bus->dispatch(new StartProcessMessage(
            siteIdentifier: $site->getIdentifier(),
            technology: IndexTechnology::Frontend,
            type: IndexType::Full,
            indexConfigurationRecordId: $configuration->configurationId,
            indexProcessId: $id,
        ));

        // @todo use file traversing and

        $this->bus->dispatch(new FrontendIndexMessage(
            siteIdentifier: $site->getIdentifier(),
            technology: IndexTechnology::Frontend,
            type: IndexType::Full,
            indexConfigurationRecordId: $configuration->configurationId,
        ));

        $this->fileIndexing->fillQueue($configuration);

        $this->bus->dispatch(new FinishProcessMessage(
            siteIdentifier: $site->getIdentifier(),
            technology: IndexTechnology::Frontend,
            type: IndexType::Full,
            indexConfigurationRecordId: $configuration->configurationId,
            indexProcessId: $id,
        ));
    }

}
