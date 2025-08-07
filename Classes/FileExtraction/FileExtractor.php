<?php

declare(strict_types=1);

namespace Lochmueller\Index\FileExtraction;

use Lochmueller\Index\FileExtraction\Extractor\FileExtractionInterface;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;
use TYPO3\CMS\Core\Resource\FileInterface;

class FileExtractor
{
    public function __construct(
        #[AutowireIterator('index.file_extractor')]
        protected iterable $fileExtractor,
    ) {}

    public function extract(FileInterface $file): ?string
    {
        foreach ($this->getExtractors() as $extractor) {
            if (in_array($file->getExtension(), $extractor->getFileExtensions(), true)) {
                return $extractor->getFileContent($file);
            }
        }
        return null;
    }

    public function resolveFileTypes(array $fileTypes): array
    {
        $extensions = [];
        foreach ($this->getExtractors() as $extractor) {
            foreach ($fileTypes as $fileType) {
                if ($extractor->getFileGroupName() === $fileType) {
                    $extensions += $extractor->getFileExtensions();
                    continue 2;
                }
            }
        }
        return array_unique($extensions);
    }

    /**
     * @return FileExtractionInterface[]
     */
    public function getExtractors(): iterable
    {
        yield from $this->fileExtractor;
    }
}
