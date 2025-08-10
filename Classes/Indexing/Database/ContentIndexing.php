<?php

declare(strict_types=1);

namespace Lochmueller\Index\Indexing\Database;

use Lochmueller\Index\Event\ContentType\HandleContentTypeEvent;
use Lochmueller\Index\Indexing\Database\ContentType\ContentTypeInterface;
use Lochmueller\Index\Indexing\IndexingInterface;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;
use TYPO3\CMS\Core\Domain\Record;

class ContentIndexing implements IndexingInterface, LoggerAwareInterface
{
    use LoggerAwareTrait;

    public function __construct(
        /** @var ContentTypeInterface[] */
        #[AutowireIterator('index.content_type')]
        protected iterable                 $contentTypes,
        protected EventDispatcherInterface $eventDispatcher,
    ) {}

    public function getContent(Record $record): ?string
    {
        $content = null;
        $defaultHandled = false;
        foreach ($this->contentTypes as $contentType) {
            if ($contentType->canHandle($record)) {
                $content = $contentType->getContent($record);
                $defaultHandled = true;
                break;
            }
        }

        $handleEvent = new HandleContentTypeEvent($record, $defaultHandled, $content);
        $handleEvent = $this->eventDispatcher->dispatch($handleEvent);

        if ($handleEvent->content === null) {
            $this->logger->warning('Content could not be handled', ['record_type' => $record->getRecordType()]);
        }

        return $handleEvent->content;
    }

}
