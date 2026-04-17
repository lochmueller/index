<?php

declare(strict_types=1);

namespace Lochmueller\Index\ContentProcessing;

interface ContentProcessorInterface
{
    public function process(string $htmlContent): string;
}
