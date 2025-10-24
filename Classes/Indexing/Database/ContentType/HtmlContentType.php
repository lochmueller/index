<?php

declare(strict_types=1);

namespace Lochmueller\Index\Indexing\Database\ContentType;

use Lochmueller\Index\Indexing\Database\DatabaseIndexingDto;
use TYPO3\CMS\Core\Domain\Record;

class HtmlContentType extends SimpleContentType
{
    public function __construct(protected HeaderContentType $headerContentType) {}

    public function canHandle(Record $record): bool
    {
        return $record->getRecordType() === 'html';
    }

    public function addContent(Record $record, DatabaseIndexingDto $dto): void
    {
        $dto->content .= $record->get('bodytext');
    }
}
