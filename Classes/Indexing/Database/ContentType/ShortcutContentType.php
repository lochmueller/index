<?php

declare(strict_types=1);

namespace Lochmueller\Index\Indexing\Database\ContentType;

use Lochmueller\Index\Indexing\Database\ContentIndexing;
use Lochmueller\Index\Indexing\Database\DatabaseIndexingDto;
use TYPO3\CMS\Core\Domain\Record;

class ShortcutContentType extends SimpleContentType
{
    public function __construct(
        protected ContentIndexing $contentIndexing,
    ) {}

    public function canHandle(Record $record): bool
    {
        return $record->getRecordType() === 'shortcut';
    }

    public function addContent(Record $record, DatabaseIndexingDto $dto): void
    {
        foreach ($record->get('records') as $internalRecord) {
            $this->contentIndexing->addContent($internalRecord, $dto);
        }
    }
}
