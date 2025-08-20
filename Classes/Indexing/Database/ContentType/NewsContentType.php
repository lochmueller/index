<?php

declare(strict_types=1);

namespace Lochmueller\Index\Indexing\Database\ContentType;

use Lochmueller\Index\Indexing\Database\ContentIndexing;
use Lochmueller\Index\Indexing\Database\DatabaseIndexingDto;
use Lochmueller\Index\Traversing\RecordSelection;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Domain\Record;

class NewsContentType implements ContentTypeInterface
{
    public function __construct(
        protected HeaderContentType $headerContentType,
        protected RecordSelection   $recordSelection,
        protected ContentIndexing   $contentIndexing,
    ) {}

    public function canHandle(Record $record): bool
    {
        return $record->getRecordType() === 'news_pi1' || $record->getRecordType() === 'news_newsdetail';
    }

    public function addContent(Record $record, DatabaseIndexingDto $dto): void
    {
        $newsId = $dto->arguments['tx_news_pi1']['news'] ?? 0;
        if ($newsId <= 0) {
            return;
        }

        $this->headerContentType->addContent($record, $dto);
        $table = 'tx_news_domain_model_news';
        $newsRecord = $this->recordSelection->mapRecord($table, BackendUtility::getRecord('tx_news_domain_model_news', $newsId));

        $dto->title = $newsRecord->get('title') . ' | ' . $dto->site->getAttribute('websiteTitle');

        $dto->content .= $newsRecord->get('title') . ' ';
        $dto->content .= $newsRecord->get('teaser') . ' ';
        $dto->content .= $newsRecord->get('bodytext') . ' ';

        foreach ($newsRecord->get('content_elements') as $ce) {
            $this->contentIndexing->addContent($ce, $dto);
        }
    }

    public function addVariants(Record $record, \SplQueue &$queue): void
    {
        /** @var DatabaseIndexingDto $dto */
        $dto = $queue->offsetGet(0);
        $queue = new \SplQueue();

        foreach ($this->getNewsRecords($record, $dto->languageUid) as $record) {
            if ($record->getRecordType() === '0') {
                $arguments = [
                    '_language' => $dto->languageUid,
                    'tx_news_pi1' => [
                        'action' => 'detail',
                        'controller' => 'News',
                        'news' => $record->getUid(),
                    ],
                ];

                $queue[] = new DatabaseIndexingDto($dto->title, $dto->content, $dto->pageUid, $dto->languageUid, $arguments, $dto->site);
            }
        }

    }

    protected function getNewsRecords(Record $record, int $languageUid): iterable
    {

        /** @var \TYPO3\CMS\Core\Domain\FlexFormFieldValues $flexFormConfiguration */
        $flexFormConfiguration = $record->get('pi_flexform');
        $array = $flexFormConfiguration->toArray();

        $storage = [-99];
        foreach ($array['sDEF']['settings']['startingpoint'] ?? [] as $page) {
            $storage[] = $page->get('uid');
        }

        yield from $this->recordSelection->findRecordsOnPage('tx_news_domain_model_news', $storage, $languageUid);
    }
}
