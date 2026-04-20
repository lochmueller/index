<?php

declare(strict_types=1);

namespace Lochmueller\Index\ContentProcessing;

use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

#[Autoconfigure(public: true)]
readonly class ContentProcessor
{
    /**
     * @param iterable<ContentProcessorInterface> $contentProcessors
     */
    public function __construct(
        #[AutowireIterator('index.content_processor')]
        protected iterable $contentProcessors,
    ) {}

    /**
     * @param array<string, mixed> $params
     */
    public function getBackendItems(array &$params): void
    {
        foreach ($this->contentProcessors as $processor) {
            $params['items'][] = [
                'label' => $processor->getLabel(),
                'value' => $processor::class,
            ];
        }
    }

    /**
     * @param class-string[] $activeProcessors
     */
    public function process(string $content, array $activeProcessors): string
    {
        foreach ($this->contentProcessors as $processor) {
            if (in_array($processor::class, $activeProcessors, true)) {
                $content = $processor->process($content);
            }
        }

        return $content;
    }
}
