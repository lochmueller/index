<?php

declare(strict_types=1);

namespace Lochmueller\Index\Indexing\Database;

use Lochmueller\Index\Configuration\ConfigurationLoader;
use Lochmueller\Index\Event\IndexPageEvent;
use Lochmueller\Index\Indexing\IndexingInterface;
use Lochmueller\Index\Queue\Message\DatabaseIndexMessage;
use Lochmueller\Index\Traversing\RecordSelection;
use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Site\Entity\Site;
use TYPO3\CMS\Core\Site\SiteFinder;

class DatabaseIndexingHandler implements IndexingInterface
{
    public function __construct(
        private SiteFinder                        $siteFinder,
        private ContentIndexing                   $contentIndexing,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly RecordSelection          $recordSelection,
        private readonly ConfigurationLoader $configurationLoader,
    ) {}

    #[AsMessageHandler]
    public function __invoke(DatabaseIndexMessage $message): void
    {
        /** @var Site $site */
        $site = $this->siteFinder->getSiteByIdentifier($message->siteIdentifier);

        $configuration = $this->configurationLoader->loadByUid($message->indexConfigurationRecordId);
        $pageRow = BackendUtility::getRecord('pages', $message->pageUid);
        if ($configuration->skipNoSearchPages && $pageRow['no_search'] ?? false) {
            return;
        }

        $title = $pageRow['title'] . ' | ' . $site->getAttribute('websiteTitle');
        $language = 0;
        $accessGroups = [];

        $mainContent = '';
        foreach ($this->recordSelection->findRecordsOnPage('tt_content', [$message->pageUid]) as $record) {
            $mainContent .= $this->contentIndexing->getContent($record);
        }

        $this->eventDispatcher->dispatch(new IndexPageEvent(
            site: $site,
            technology: $message->technology,
            type: $message->type,
            indexConfigurationRecordId: $message->indexConfigurationRecordId,
            language: $language,
            title: $title,
            content: $mainContent,
            pageUid: $message->pageUid,
            indexProcessId: $message->indexProcessId,
            accessGroups: $accessGroups,
        ));
    }

}
