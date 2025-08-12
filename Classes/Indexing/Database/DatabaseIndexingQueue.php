<?php

declare(strict_types=1);

namespace Lochmueller\Index\Indexing\Database;

use Lochmueller\Index\Configuration\Configuration;
use Lochmueller\Index\Enums\IndexTechnology;
use Lochmueller\Index\Enums\IndexType;
use Lochmueller\Index\Indexing\File\FileIndexingQueue;
use Lochmueller\Index\Indexing\IndexingInterface;
use Lochmueller\Index\Queue\Message\DatabaseIndexMessage;
use Lochmueller\Index\Queue\Message\FinishProcessMessage;
use Lochmueller\Index\Queue\Message\StartProcessMessage;
use Lochmueller\Index\Traversing\PageTraversing;
use Symfony\Component\Messenger\MessageBusInterface;
use TYPO3\CMS\Core\Site\SiteFinder;

class DatabaseIndexingQueue implements IndexingInterface
{
    public function __construct(
        private MessageBusInterface $bus,
        private SiteFinder          $siteFinder,
        private PageTraversing      $pageTraversing,
        private FileIndexingQueue   $fileIndexing,
    ) {}

    public function fillQueue(Configuration $configuration): void
    {
        $site = $this->siteFinder->getSiteByPageId($configuration->pageId);

        $id = uniqid('cache-index', true);
        $this->bus->dispatch(new StartProcessMessage(
            siteIdentifier: $site->getIdentifier(),
            technology: IndexTechnology::Database,
            type: IndexType::Full,
            indexConfigurationRecordId: $configuration->configurationId,
            indexProcessId: $id,
        ));


        $frontendInformation = $this->pageTraversing->getFrontendInformation($configuration);

        foreach ($frontendInformation as $info) {
            $this->bus->dispatch(new DatabaseIndexMessage(
                siteIdentifier: $site->getIdentifier(),
                technology: IndexTechnology::Database,
                type: IndexType::Full,
                indexConfigurationRecordId: $configuration->configurationId,
                uri: $info['uri'],
                pageUid: $info['pageUid'],
            ));
        }

        $this->fileIndexing->fillQueue($configuration, $site);

        $this->bus->dispatch(new FinishProcessMessage(
            siteIdentifier: $site->getIdentifier(),
            technology: IndexTechnology::Database,
            type: IndexType::Full,
            indexConfigurationRecordId: $configuration->configurationId,
            indexProcessId: $id,
        ));
    }


}
