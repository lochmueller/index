<?php

declare(strict_types=1);

namespace Lochmueller\Index\Event\Extractor;

use TYPO3\CMS\Core\Resource\FileInterface;

class CustomFileExtraction
{
    public function __construct(protected readonly FileInterface $file, public ?string $content = null) {}

}
