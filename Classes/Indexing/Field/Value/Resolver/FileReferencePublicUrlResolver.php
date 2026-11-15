<?php

declare(strict_types=1);

namespace Lochmueller\Index\Indexing\Field\Value\Resolver;

use Symfony\Component\DependencyInjection\Attribute\AsTaggedItem;
use TYPO3\CMS\Core\Resource\FileReference;

#[AsTaggedItem(index: 'file_reference_public_url')]
final class FileReferencePublicUrlResolver extends AbstractFileReferenceResolver
{
    protected function getTargetType(): string
    {
        return 'text';
    }

    protected function resolveFileReference(FileReference $fileReference): ?string
    {
        return $fileReference->getPublicUrl();
    }
}
