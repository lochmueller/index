<?php

declare(strict_types=1);

namespace Lochmueller\Index\Indexing\Database\ContentType;

use Lochmueller\Index\Indexing\Database\DatabaseIndexingDto;
use TYPO3\CMS\Core\Domain\Record;
use TYPO3\CMS\Core\Resource\Collection\LazyFileReferenceCollection;
use TYPO3\CMS\Core\Resource\FileReference;

class ImageContentType extends SimpleContentType
{
    public function __construct(protected HeaderContentType $headerContentType) {}

    public function canHandle(Record $record): bool
    {
        return $record->getRecordType() === 'image';
    }

    public function addContent(Record $record, DatabaseIndexingDto $dto): void
    {
        $this->headerContentType->addContent($record, $dto);
        /** @var LazyFileReferenceCollection $images */
        $images = $record->get('image');

        $result = [];
        foreach ($images->getIterator() as $image) {
            /** @var $image FileReference */
            $result[] = $image->getTitle();
            $result[] = $image->getDescription();
        }

        $dto->content .= implode(' ', $result);
    }
}
