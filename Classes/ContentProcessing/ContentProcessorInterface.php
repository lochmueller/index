<?php

declare(strict_types=1);

namespace Lochmueller\Index\ContentProcessing;

use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[AutoconfigureTag(name: 'index.content_processor')]
interface ContentProcessorInterface
{
    public function getLabel(): string;

    public function process(string $htmlContent): string;
}
