<?php

declare(strict_types=1);

namespace Lochmueller\Index\Indexing\Database\ContentType;

use Lochmueller\Index\Database\Query\Restriction\ContainerElementsRestrictionContainer;
use Lochmueller\Index\Indexing\Database\ContentIndexing;
use Lochmueller\Index\Indexing\Database\DatabaseIndexingDto;
use Lochmueller\Index\Traversing\RecordSelection;
use TYPO3\CMS\Core\Database\Query\Restriction\FrontendRestrictionContainer;
use TYPO3\CMS\Core\Domain\Record;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class ContainerContentType extends SimpleContentType
{
    public function __construct(
        protected RecordSelection $recordSelection,
        protected ContentIndexing $contentIndexing,
    ) {}

    public function canHandle(Record $record): bool
    {
        return str_starts_with((string) $record->getRecordType(), 'container_');
    }

    public function addContent(Record $record, DatabaseIndexingDto $dto): void
    {
        $dto->content .= '<section id="container_' . $record->get('uid') . '">';
        foreach ($this->getContainerRecords($record, $record->getLanguageId() ?? 0) as $ce) {
            $dto->content .= '<div id="section_' . $ce->get('uid') . '">';
            $this->contentIndexing->addContent($ce, $dto);
            $dto->content .= '</div>';
        }
        $dto->content .= '</section>';
    }

    /**
     * @return iterable<Record>
     */
    protected function getContainerRecords(Record $record, int $languageUid): iterable
    {
        $restriction = [
            FrontendRestrictionContainer::class,
            GeneralUtility::makeInstance(ContainerElementsRestrictionContainer::class, $record->get('uid')),
        ];
        yield from $this->recordSelection->findRecordsOnPage('tt_content', [$record->get('pid')], $languageUid, $restriction);
    }
}
