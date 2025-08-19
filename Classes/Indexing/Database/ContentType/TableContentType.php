<?php

declare(strict_types=1);

namespace Lochmueller\Index\Indexing\Database\ContentType;

use TYPO3\CMS\Core\Domain\Record;

class TableContentType implements ContentTypeInterface
{
    public function __construct(protected HeaderContentType $headerContentType) {}

    public function canHandle(Record $record): bool
    {
        return $record->getRecordType() === 'table';
    }

    public function getContent(Record $record): string
    {

        return '<div>' . $this->headerContentType->getContent($record) . ' - ' . $record->get('bodytext') . '</div>';
    }
}
