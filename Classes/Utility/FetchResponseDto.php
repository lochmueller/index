<?php

declare(strict_types=1);

namespace Lochmueller\Index\Utility;

final readonly class FetchResponseDto
{
    public function __construct(
        public string $uri,
        public string $contentType,
        public string $content,
    ) {}

    public function isHtml(): bool
    {
        if ($this->contentType === '') {
            return (bool) preg_match('/<html[\s>]/i', $this->content);
        }

        return str_contains($this->contentType, 'text/html') || str_contains($this->contentType, 'application/xhtml+xml');
    }
}
