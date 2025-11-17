<?php

declare(strict_types=1);

namespace Lochmueller\Index\Indexing\External;

use Lochmueller\Index\Enums\IndexTechnology;
use Lochmueller\Index\Enums\IndexType;
use Lochmueller\Index\Indexing\IndexingInterface;
use Lochmueller\Index\Queue\Bus;
use Lochmueller\Index\Queue\Message\ExternalFileIndexMessage;
use Lochmueller\Index\Queue\Message\ExternalPageIndexMessage;
use Lochmueller\Index\Queue\Message\FinishProcessMessage;
use Lochmueller\Index\Queue\Message\StartProcessMessage;
use TYPO3\CMS\Core\Site\Entity\SiteInterface;
use TYPO3\CMS\Core\Site\SiteFinder;

readonly class ExternalIndexingQueue implements IndexingInterface
{
    public function __construct(
        protected Bus        $bus,
        protected SiteFinder $siteFinder,
    ) {}

    public function fillQueue(SiteInterface $site, int $language, array $info, bool $isPage = false): void
    {
        $id = uniqid('external-index', true);
        $this->bus->dispatch(new StartProcessMessage(
            siteIdentifier: $site->getIdentifier(),
            technology: IndexTechnology::External,
            type: IndexType::Partial,
            indexConfigurationRecordId: null,
            indexProcessId: $id,
        ));

        if ($isPage) {
            $this->bus->dispatch(new ExternalPageIndexMessage(
                siteIdentifier: $site->getIdentifier(),
                language: $language,
                technology: IndexTechnology::External,
                type: IndexType::Partial,
                uri: $info['uri'] ?? '',
                title: $info['title'] ?? '',
                content: $info['content'] ?? '',
                indexProcessId: $id,
            ));
        } else {
            $this->bus->dispatch(new ExternalFileIndexMessage(
                siteIdentifier: $site->getIdentifier(),
                language: $language,
                technology: IndexTechnology::External,
                type: IndexType::Partial,
                uri: $info['uri'] ?? '',
                title: $info['title'] ?? '',
                content: $info['content'] ?? '',
                indexProcessId: $id,
            ));
        }

        $this->bus->dispatch(new FinishProcessMessage(
            siteIdentifier: $site->getIdentifier(),
            technology: IndexTechnology::External,
            type: IndexType::Partial,
            indexConfigurationRecordId: null,
            indexProcessId: $id,
        ));
    }

}
