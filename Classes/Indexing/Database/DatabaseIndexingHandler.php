<?php

declare(strict_types=1);

namespace Lochmueller\Index\Indexing\Database;

use Lochmueller\Index\Configuration\ConfigurationLoader;
use Lochmueller\Index\Event\IndexPageEvent;
use Lochmueller\Index\Indexing\IndexingInterface;
use Lochmueller\Index\Queue\Message\DatabaseIndexMessage;
use Lochmueller\Index\Traversing\RecordSelection;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use TYPO3\CMS\Core\Site\Entity\Site;
use TYPO3\CMS\Core\Site\SiteFinder;

class DatabaseIndexingHandler implements IndexingInterface, LoggerAwareInterface
{
    use LoggerAwareTrait;

    public function __construct(
        private SiteFinder                        $siteFinder,
        private ContentIndexing                   $contentIndexing,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly RecordSelection          $recordSelection,
        private readonly ConfigurationLoader      $configurationLoader,
    ) {}

    #[AsMessageHandler]
    public function __invoke(DatabaseIndexMessage $message): void
    {
        try {
            $site = $this->siteFinder->getSiteByIdentifier($message->siteIdentifier);

            $configuration = $this->configurationLoader->loadByUid($message->indexConfigurationRecordId);
            $pageRow = $this->recordSelection->findRenderablePage($message->pageUid, $message->language);
            if ($pageRow === null) {
                return;
            }
            if ($configuration->skipNoSearchPages && ($pageRow['no_search'] ?? false)) {
                return;
            }

            $title = $pageRow['title'] . ' | ' . $site->getAttribute('websiteTitle');
            $accessGroups = [];

            $contentElements = $this->recordSelection->findRecordsOnPage('tt_content', [$message->pageUid], $message->language);

            if ($configuration->contentIndexing) {
                foreach ($contentElements as $record) {
                    $items = new \SplQueue();
                    $items[] = new DatabaseIndexingDto($title, '', $message->pageUid, $message->language, [], $site);

                    $this->contentIndexing->getVariants($record, $items);
                    foreach ($items as $item) {
                        $this->contentIndexing->addContent($record, $item);
                    }

                    $this->indexItems($items, $message, $site, $accessGroups, 'c' . $record->getUid());
                }
            } else {
                $items = new \SplQueue();
                $items[] = new DatabaseIndexingDto($title, '', $message->pageUid, $message->language, [], $site);

                foreach ($contentElements as $record) {
                    $this->contentIndexing->getVariants($record, $items);
                    foreach ($items as $item) {
                        $this->contentIndexing->addContent($record, $item);
                    }
                }

                $this->indexItems($items, $message, $site, $accessGroups);
            }
        } catch (\Exception $exception) {
            $this->logger->error($exception->getMessage(), ['exception' => $exception]);
        }
    }

    protected function indexItems(\SplQueue $items, DatabaseIndexMessage $message, Site $site, $accessGroups, $fragment = '')
    {
        foreach ($items as $item) {
            $item->arguments['_language'] = $message->language;
            $this->eventDispatcher->dispatch(new IndexPageEvent(
                site: $site,
                technology: $message->technology,
                type: $message->type,
                indexConfigurationRecordId: $message->indexConfigurationRecordId,
                indexProcessId: $message->indexProcessId,
                language: $message->language,
                title: $item->title,
                content: $item->content,
                pageUid: $item->pageUid,
                accessGroups: $accessGroups,
                uri: (string) $site->getRouter()->generateUri($item->pageUid, $item->arguments, $fragment),
            ));
        }
    }

}
