<?php

declare(strict_types=1);

namespace Lochmueller\Index\Indexing\Database\ContentType;

use HDNET\Calendarize\Domain\Model\Index;
use HDNET\Calendarize\Domain\Repository\IndexRepository;
use Lochmueller\Index\Indexing\Database\ContentIndexing;
use Lochmueller\Index\Indexing\Database\DatabaseIndexingDto;
use Lochmueller\Index\Traversing\RecordSelection;
use TYPO3\CMS\Core\Domain\FlexFormFieldValues;
use TYPO3\CMS\Core\Domain\Record;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\View\ViewFactoryData;
use TYPO3\CMS\Core\View\ViewFactoryInterface;

class CalendarizeContentType implements ContentTypeInterface
{
    public function __construct(
        protected RecordSelection      $recordSelection,
        protected ContentIndexing      $contentIndexing,
        protected ViewFactoryInterface $viewFactory,
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

        /** @var IndexRepository $indexRepository */
        $indexRepository = GeneralUtility::makeInstance(IndexRepository::class);
        /** @var Index|null $indexObject */
        $indexObject = $indexRepository->findByUid($index);

        if (!$indexObject) {
            return;
        }
        $originalObject = $indexObject->getOriginalObject();

        if (!$originalObject) {
            return;
        }

        $viewData = new ViewFactoryData(
            templateRootPaths: [
                'EXT:calendarize/Resources/Private/Templates',
            ],
            partialRootPaths: [
                'EXT:calendarize/Resources/Private/Partials',
            ],
            layoutRootPaths: [
                'EXT:calendarize/Resources/Private/Layouts/',
            ],
            format: 'html',
        );

        $view = $this->viewFactory->create($viewData);
        $view->assignMultiple([
            'index' => $index,
        ]);

        $dto->content .= $view->render('Calendar/Detail');
    }


    /**
     * @param \SplQueue<DatabaseIndexingDto> $queue
     */
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

    /**
     * @return iterable<Record>
     */
    protected function getIndexRecords(Record $record, int $languageUid): iterable
    {
        /** @var FlexFormFieldValues $flexFormConfiguration */
        $flexFormConfiguration = $record->get('pi_flexform');
        $array = $flexFormConfiguration->toArray();

        $storagePids = $array['general']['persistence']['storagePid'] ?? '';

        $storage = GeneralUtility::intExplode(',', (string) $storagePids, true);
        if ($storage === []) {
            $storage = [-99];
        }

        yield from $this->recordSelection->findRecordsOnPage('tx_calendarize_domain_model_index', $storage, $languageUid);
    }
}
