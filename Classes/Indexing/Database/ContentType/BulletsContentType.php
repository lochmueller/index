<?php

declare(strict_types=1);

namespace Lochmueller\Index\Indexing\Database\ContentType;

use TYPO3\CMS\Core\Domain\Record;

class BulletsContentType implements ContentTypeInterface
{
    public function __construct(protected HeaderContentType $headerContentType) {}

    public function canHandle(Record $record): bool
    {
        return $record->getRecordType() === 'bullets';
    }

    public function getContent(Record $record): string
    {

        return '<div>' . $this->headerContentType->getContent($record) . ' - ' . $record->get('bodytext') . '</div>';
    }
}
