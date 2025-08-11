<?php

declare(strict_types=1);

namespace Lochmueller\Index\Configuration;

use Lochmueller\Index\Enums\IndexTechnology;

class Configuration
{
    public function __construct(
        public readonly int $configurationId,
        public readonly int $pageId,
        public readonly IndexTechnology $technology,
        public readonly bool $skipNoSearchPages,
        public readonly array $fileMounts,
        public readonly array $fileTypes,
        public readonly array $configuration,
    ) {}

}
