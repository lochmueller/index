<?php

declare(strict_types=1);

namespace Lochmueller\Index\Event\Extractor;

class CustomExtensionsFileExtraction
{
    public function __construct(public array $fileExtensions = []) {}

}
