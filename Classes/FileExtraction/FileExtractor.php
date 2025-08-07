<?php

declare(strict_types=1);

namespace Lochmueller\Index\FileExtraction;

use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;
use TYPO3\CMS\Core\Resource\FileInterface;

class FileExtractor
{
    public function __construct(
        #[AutowireIterator('index.file_extractor')]
        protected iterable $fileExtractor,
    ) {}

    public function extract(FileInterface $fileInterface): ?string
    {
        foreach ($this->getExtractors() as $extractor) {

        }
        return null;
    }

    public function getExtractors(): iterable
    {
        yield from $this->fileExtractor;
    }
}
