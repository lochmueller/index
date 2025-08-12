<?php

declare(strict_types=1);

namespace Lochmueller\Index\Indexing\Database\ContentType;

use TYPO3\CMS\Core\Domain\Record;

class TextpicContentType implements ContentTypeInterface
{
    public function __construct(
        protected HeaderContentType $headerContentType,
        protected TextContentType   $textContentType,
        protected ImageContentType  $imageContentType,
    ) {}

    public function canHandle(Record $record): bool
    {
        return $record->getRecordType() === 'textpic';
    }

    public function getContent(Record $record): string
    {
        return $this->headerContentType->getContent($record) . $this->textContentType->getContent($record) . $this->imageContentType->getContent($record);
    }
}
