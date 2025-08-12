<?php

declare(strict_types=1);

namespace Lochmueller\Index\Indexing\Database\ContentType;

use TYPO3\CMS\Core\Domain\Record;
use TYPO3\CMS\Core\Resource\Collection\LazyFileReferenceCollection;
use TYPO3\CMS\Core\Resource\FileReference;

class ImageContentType implements ContentTypeInterface
{
    public function __construct(protected HeaderContentType $headerContentType) {}

    public function canHandle(Record $record): bool
    {
        return $record->getRecordType() === 'image';
    }

    public function getContent(Record $record): string
    {

        /** @var LazyFileReferenceCollection $images */
        $images = $record->get('image');

        $result = [];
        foreach ($images->getIterator() as $image) {
            /** @var $image FileReference */
            $result[] = $image->getTitle();
            $result[] = $image->getDescription();
        }

        return '<div>' . implode(' ', $result) . '</div>';
    }
}
