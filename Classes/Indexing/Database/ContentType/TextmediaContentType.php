<?php

declare(strict_types=1);

namespace Lochmueller\Index\Indexing\Database\ContentType;

use Lochmueller\Index\Indexing\Database\DatabaseIndexingDto;
use TYPO3\CMS\Core\Domain\Record;

class TextmediaContentType extends SimpleContentType
{
    public function __construct(
        protected HeaderContentType $headerContentType,
        protected TextContentType   $textContentType,
        protected MediaContentType  $mediaContentType,
    ) {}

    public function canHandle(Record $record): bool
    {
        return $record->getRecordType() === 'textmedia';
    }

    public function addContent(Record $record, DatabaseIndexingDto $dto): void
    {
        $this->headerContentType->addContent($record, $dto);
        $this->textContentType->addContent($record, $dto);
        $this->mediaContentType->addContent($record, $dto);
    }
}
