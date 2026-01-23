<?php

declare(strict_types=1);

namespace Lochmueller\Index\Indexing\Frontend;

use Lochmueller\Index\Configuration\Configuration;
use Lochmueller\Index\Enums\IndexTechnology;
use Lochmueller\Index\Enums\IndexType;
use Lochmueller\Index\Indexing\File\FileIndexingQueue;
use Lochmueller\Index\Indexing\IndexingInterface;
use Lochmueller\Index\Queue\Bus;
use Lochmueller\Index\Queue\Message\FinishProcessMessage;
use Lochmueller\Index\Queue\Message\FrontendIndexMessage;
use Lochmueller\Index\Queue\Message\StartProcessMessage;
use Lochmueller\Index\Traversing\PageTraversing;
use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;
use TYPO3\CMS\Core\Site\SiteFinder;

#[Autoconfigure(lazy: true)]
readonly class FrontendIndexingQueue implements IndexingInterface
{
    public function __construct(
        private Bus               $bus,
        private FileIndexingQueue $fileIndexing,
        private PageTraversing    $pageTraversing,
        private SiteFinder        $siteFinder,
    ) {}

    public function fillQueue(Configuration $configuration, bool $skipFiles = false): void
    {
        $site = $this->siteFinder->getSiteByPageId($configuration->pageId);

        $indexType = $configuration->overrideIndexType ?? IndexType::Full;

        $id = uniqid('frontend-index', true);
        $this->bus->dispatch(new StartProcessMessage(
            siteIdentifier: $site->getIdentifier(),
            technology: IndexTechnology::Frontend,
            type: $indexType,
            indexConfigurationRecordId: $configuration->configurationId,
            indexProcessId: $id,
        ));

        $frontendInformation = $this->pageTraversing->getFrontendInformation($configuration);
        foreach ($frontendInformation as $info) {
            $this->bus->dispatch(new FrontendIndexMessage(
                siteIdentifier: $site->getIdentifier(),
                technology: IndexTechnology::Frontend,
                type: $indexType,
                indexConfigurationRecordId: $configuration->configurationId,
                uri: $info->uri,
                pageUid: $info->pageUid,
                indexProcessId: $id,
                accessGroups: $info->accessGroups,
            ));
        }

        if (!$skipFiles) {
            $this->fileIndexing->fillQueue($configuration, $site, $id);
        }

        $this->bus->dispatch(new FinishProcessMessage(
            siteIdentifier: $site->getIdentifier(),
            technology: IndexTechnology::Frontend,
            type: $indexType,
            indexConfigurationRecordId: $configuration->configurationId,
            indexProcessId: $id,
        ));
    }

}
