<?php

declare(strict_types=1);

namespace Lochmueller\Index\Indexing\Database\ContentType;

use Lochmueller\Index\Indexing\Database\ContentIndexing;
use TYPO3\CMS\Core\Domain\Record;

class ShortcutContentType implements ContentTypeInterface
{
    public function __construct(
        protected ContentIndexing $contentIndexing,
    ) {}

    public function canHandle(Record $record): bool
    {
        return $record->getRecordType() === 'shortcut';
    }

    public function getContent(Record $record): string
    {
        $content = [];
        foreach ($record->get('records') as $records) {
            $content[] = $this->contentIndexing->getContent($records);
        }
        return implode(' ', $content);
    }
}
