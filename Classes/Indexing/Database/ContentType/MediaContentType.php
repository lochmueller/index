<?php

declare(strict_types=1);

namespace Lochmueller\Index\Indexing\Database\ContentType;

use Lochmueller\Index\Indexing\Database\DatabaseIndexingDto;
use TYPO3\CMS\Core\Domain\Record;
use TYPO3\CMS\Core\Resource\FileReference;

class MediaContentType extends SimpleContentType
{
    public function __construct(protected HeaderContentType $headerContentType) {}

    public function canHandle(Record $record): bool
    {
        return $record->getRecordType() === 'media';
    }

    public function addContent(Record $record, DatabaseIndexingDto $dto): void
    {
        $this->headerContentType->addContent($record, $dto);

        /** @var \TYPO3\CMS\Core\Resource\Collection\LazyFileReferenceCollection $mediaElements */
        $mediaElements = $record->get('assets');

        $result = [];
        foreach ($mediaElements->getIterator() as $media) {
            /** @var $media FileReference */
            $result[] = $media->getTitle();
            $result[] = $media->getDescription();
        }

        $dto->content .= implode(' ', $result);
    }
}
