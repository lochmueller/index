<?php

declare(strict_types=1);

namespace Lochmueller\Index\Indexing\Database\ContentType;

use Lochmueller\Index\Indexing\Database\DatabaseIndexingDto;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
use TYPO3\CMS\Core\Domain\Record;

#[AutoconfigureTag(name: 'index.content_type')]
interface ContentTypeInterface
{
    public function canHandle(Record $record): bool;

    public function addContent(Record $record, DatabaseIndexingDto $dto): void;

    /**
     * @param \SplQueue<DatabaseIndexingDto> $queue
     */
    public function addVariants(Record $record, \SplQueue &$queue): void;

}
