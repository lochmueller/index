<?php

declare(strict_types=1);

namespace Lochmueller\Index\Indexing\Database\ContentType;

use TYPO3\CMS\Core\Domain\Record;
use TYPO3\CMS\Core\Resource\FileReference;

class MediaContentType implements ContentTypeInterface
{
    public function __construct(protected HeaderContentType $headerContentType) {}

    public function canHandle(Record $record): bool
    {
        return $record->getRecordType() === 'media';
    }

    public function getContent(Record $record): string
    {
        /** @var \TYPO3\CMS\Core\Resource\Collection\LazyFileReferenceCollection $mediaElements */
        $mediaElements = $record->get('assets');

        $result = [];
        foreach ($mediaElements->getIterator() as $media) {
            /** @var $media FileReference */
            $result[] = $media->getTitle();
            $result[] = $media->getDescription();
        }

        return '<div>' . $this->headerContentType->getContent($record) . ' - ' . implode(' ', $result) . '</div>';
    }
}
