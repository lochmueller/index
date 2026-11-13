<?php

declare(strict_types=1);

namespace Lochmueller\Index\Indexing\Field\Value\Resolver;

use Symfony\Component\DependencyInjection\Attribute\AsTaggedItem;
use TYPO3\CMS\Core\Resource\FileReference;

#[AsTaggedItem(index: 'file_reference_uid')]
final class FileReferenceUidResolver extends AbstractFileReferenceResolver
{
    protected function getTargetType(): string
    {
        return 'integer';
    }

    protected function resolveFileReference(FileReference $fileReference): int
    {
        return $fileReference->getUid();
    }
}
