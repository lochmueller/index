<?php

declare(strict_types=1);

namespace Lochmueller\Index\Indexing\Fetch;

use Lochmueller\Index\Configuration\Configuration;
use Lochmueller\Index\Enums\IndexTechnology;
use Lochmueller\Index\Enums\IndexType;
use Lochmueller\Index\Indexing\IndexingInterface;
use Lochmueller\Index\Queue\Bus;
use Lochmueller\Index\Queue\Message\FetchIndexMessage;
use Lochmueller\Index\Queue\Message\FinishProcessMessage;
use Lochmueller\Index\Queue\Message\StartProcessMessage;
use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;
use TYPO3\CMS\Core\Site\SiteFinder;

#[Autoconfigure(lazy: true)]
readonly class FetchIndexingQueue implements IndexingInterface
{
    public function __construct(
        private Bus        $bus,
        private SiteFinder $siteFinder,
    ) {}

    public function fillQueue(Configuration $configuration, bool $skipFiles = false): void
    {
        if ($configuration->fetchUrl === '') {
            return;
        }

        $site = $this->siteFinder->getSiteByPageId($configuration->pageId);

        $indexType = $configuration->overrideIndexType ?? IndexType::Full;

        $id = uniqid('fetch-index', true);
        $this->bus->dispatch(new StartProcessMessage(
            siteIdentifier: $site->getIdentifier(),
            technology: IndexTechnology::Fetch,
            type: $indexType,
            indexConfigurationRecordId: $configuration->configurationId,
            indexProcessId: $id,
        ));

        // The crawling of the linked pages is done in one message, so all index events are
        // dispatched between the start and the finish message of the process.
        $this->bus->dispatch(new FetchIndexMessage(
            siteIdentifier: $site->getIdentifier(),
            technology: IndexTechnology::Fetch,
            type: $indexType,
            indexConfigurationRecordId: $configuration->configurationId,
            indexProcessId: $id,
            language: $site->getDefaultLanguage()->getLanguageId(),
            uri: $configuration->fetchUrl,
            depth: max(0, $configuration->fetchDepth),
            skipFiles: $skipFiles,
        ));

        $this->bus->dispatch(new FinishProcessMessage(
            siteIdentifier: $site->getIdentifier(),
            technology: IndexTechnology::Fetch,
            type: $indexType,
            indexConfigurationRecordId: $configuration->configurationId,
            indexProcessId: $id,
        ));
    }
}
