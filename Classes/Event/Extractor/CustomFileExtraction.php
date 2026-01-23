<?php

declare(strict_types=1);

namespace Lochmueller\Index\Event\Extractor;

use TYPO3\CMS\Core\Resource\FileInterface;

final class CustomFileExtraction
{
    /**
     * @param string[] $extensions
     */
    public function __construct(
        protected readonly ?FileInterface $file = null,
        public ?string $content = null,
        public array $extensions = [],
    ) {}

}
