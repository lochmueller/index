<?php

declare(strict_types=1);

namespace Lochmueller\Index\Indexing\Database\ContentType;

use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
use TYPO3\CMS\Core\Domain\Record;

#[AutoconfigureTag(name: 'index.content_type')]
interface ContentTypeInterface
{
    public function canHandle(Record $record): bool;

    public function getContent(Record $record): string;

}
