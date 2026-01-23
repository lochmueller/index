<?php

declare(strict_types=1);

namespace Lochmueller\Index\Indexing\Database\ContentType;

use Lochmueller\Index\Indexing\Database\ContentIndexing;
use Lochmueller\Index\Indexing\Database\DatabaseIndexingDto;
use Lochmueller\Index\Traversing\RecordSelection;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Domain\Record;

class CalendarizeContentType implements ContentTypeInterface
{
    public function __construct(
        protected HeaderContentType $headerContentType,
        protected RecordSelection   $recordSelection,
        protected ContentIndexing   $contentIndexing,
    ) {}

    public function canHandle(Record $record): bool
    {
        return $record->getRecordType() === 'calendarize_listdetail' || $record->getRecordType() === 'calendarize_detail';
    }

    public function addContent(Record $record, DatabaseIndexingDto $dto): void
    {
        $index = $dto->arguments['tx_calendarize_calendar']['index'] ?? 0;
        if ($index <= 0) {
            return;
        }

        // @todo Integrate Calendarize detail rendering
        /*
        $this->headerContentType->addContent($record, $dto);
        $table = 'tx_news_domain_model_news';
        $newsRecord = $this->recordSelection->mapRecord($table, BackendUtility::getRecord($table, $newsId));

        $dto->title = $newsRecord->get('title') . ' | ' . $dto->site->getAttribute('websiteTitle');

        $dto->content .= $newsRecord->get('title') . ' ';
        $dto->content .= $newsRecord->get('teaser') . ' ';
        $dto->content .= $newsRecord->get('bodytext') . ' ';

        foreach ($newsRecord->get('content_elements') as $ce) {
            $this->contentIndexing->addContent($ce, $dto);
        }
        */
    }

    public function addVariants(Record $record, \SplQueue &$queue): void
    {
        /** @var DatabaseIndexingDto $dto */
        $dto = $queue->offsetGet(0);
        $queue = new \SplQueue();

        foreach ($this->getIndexRecords($record, $dto->languageUid) as $record) {
            if ($record->getRecordType() === '0') {
                $arguments = [
                    '_language' => $dto->languageUid,
                    'tx_calendarize_calendar' => [
                        'action' => 'detail',
                        'controller' => 'Calendar',
                        'index' => $record->getUid(),
                    ],
                ];

                $queue[] = new DatabaseIndexingDto($dto->title, $dto->content, $dto->pageUid, $dto->languageUid, $arguments, $dto->site);
            }
        }
    }

    protected function getIndexRecords(Record $record, int $languageUid): iterable
    {
        // @todo integrate calendarize index selection
        /** @var \TYPO3\CMS\Core\Domain\FlexFormFieldValues $flexFormConfiguration */
        $flexFormConfiguration = $record->get('pi_flexform');
        $array = $flexFormConfiguration->toArray();

        $storage = [-99];
        # foreach ($array['sDEF']['settings']['startingpoint'] ?? [] as $page) {
        #   $storage[] = $page->get('uid');
        # }

        yield from $this->recordSelection->findRecordsOnPage('tx_calendarize_domain_model_index', $storage, $languageUid);
    }
}
