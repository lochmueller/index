<?php

declare(strict_types=1);

namespace Lochmueller\Index\Indexing\Database\ContentType;

use TYPO3\CMS\Core\Domain\Record;

class TextContentType implements ContentTypeInterface
{
    public function __construct(protected HeaderContentType $headerContentType) {}

    public function canHandle(Record $record): bool
    {
        return $record->getRecordType() === 'text';
    }

    public function getContent(Record $record): string
    {
        $return = $this->headerContentType->getContent($record);
        return '<div>' . $record->get('bodytext') . '</div>';
    }
}
