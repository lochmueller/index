<?php

declare(strict_types=1);

namespace Lochmueller\Index\Indexing\Database\ContentType;

use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
use TYPO3\CMS\Core\Domain\Record;

#[AutoconfigureTag(name: 'index.content_type')]
abstract class SimpleContentType implements ContentTypeInterface
{
    public function addVariants(Record $record, \SplQueue &$queue): void {}

}
