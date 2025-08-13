<?php

declare(strict_types=1);

namespace Lochmueller\Index\Indexing\Database\ContentType;

use TYPO3\CMS\Core\Domain\Record;

class NewsContentType implements ContentTypeInterface
{
    public function __construct(
        protected HeaderContentType $headerContentType,
        protected TextContentType   $textContentType,
        protected ImageContentType  $imageContentType,
    ) {}

    public function canHandle(Record $record): bool
    {
        return $record->getRecordType() === 'news_pi1' || $record->getRecordType() === 'news_newsdetail';
    }

    public function getContent(Record $record): string
    {
        // @todo implement DB resolving
        return 'Dummy';
    }
}
