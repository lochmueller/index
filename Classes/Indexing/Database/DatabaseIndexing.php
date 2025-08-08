<?php

declare(strict_types=1);

namespace Lochmueller\Index\Indexing\Database;

use Lochmueller\Index\Configuration\Configuration;
use Lochmueller\Index\Enums\IndexTechnology;
use Lochmueller\Index\Enums\IndexType;
use Lochmueller\Index\Indexing\File\FileIndexing;
use Lochmueller\Index\Indexing\IndexingInterface;
use Lochmueller\Index\Queue\Message\CachePageMessage;
use Lochmueller\Index\Queue\Message\DatabaseIndexMessage;
use Lochmueller\Index\Queue\Message\FinishProcessMessage;
use Lochmueller\Index\Queue\Message\StartProcessMessage;
use Lochmueller\Index\Traversing\PageTraversing;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Yaml\Yaml;
use TYPO3\CMS\Core\Domain\RecordFactory;
use TYPO3\CMS\Core\Site\SiteFinder;

class DatabaseIndexing implements IndexingInterface
{
    public function __construct(
        private MessageBusInterface $bus,
        private SiteFinder          $siteFinder,
        private PageTraversing      $pageTraversing,
        private FileIndexing        $fileIndexing,
        private RecordFactory       $recordFactory,
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


        $indexConfiguration = Yaml::parse($configuration->configurationYaml);

        // @todo use file traversing and

        $this->bus->dispatch(new DatabaseIndexMessage());
        /*$this->bus->dispatch(new CachePageMessage(
            siteIdentifier: $site->getIdentifier(),
            technology: IndexTechnology::Cache,
            type: IndexType::Partial,
            indexConfigurationRecordId: $configuration->configurationId,
            language: (int)$this->context->getAspect('language')->getId(),
            title: $this->pageTitleProviderManager->getTitle($request),
            content: $tsfe->content,
            pageUid: (int)$pageInformation->getId(),
            accessGroups: $this->context->getPropertyFromAspect('frontend.user', 'groupIds', [0, -1]),
        ));*/

        $this->fileIndexing->fillQueue($configuration);

        $this->bus->dispatch(new FinishProcessMessage(
            siteIdentifier: $site->getIdentifier(),
            technology: IndexTechnology::Database,
            type: IndexType::Full,
            indexConfigurationRecordId: $configuration->configurationId,
            indexProcessId: $id,
        ));

    }


    #[AsMessageHandler]
    public function handleMessage(DatabaseIndexMessage $message): void
    {

        # var_dump('HANDLE DB Index');

        // Record!!!!
        // $this->recordFactory->createRawRecord('pages', )
        // @todo integrate

        // RECORD API
    }

}
