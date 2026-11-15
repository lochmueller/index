<?php

declare(strict_types=1);

namespace Lochmueller\Index\Indexing\Field\Value;

use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\Domain\RecordInterface;
use TYPO3\CMS\Core\Schema\Field\FieldTypeInterface;
use TYPO3\CMS\Core\Site\Entity\SiteInterface;

final readonly class FieldValueContext
{
    /**
     * @param array{source: string, type: string, multiple: bool} $definition
     * @param array<string, mixed> $configuration
     */
    public function __construct(
        public RecordInterface $record,
        public FieldTypeInterface $tcaField,
        public string $targetField,
        public string $sourceField,
        public array $definition,
        public array $configuration,
        public mixed $value,
        public ?SiteInterface $site = null,
        public ?int $languageId = null,
        public ?ServerRequestInterface $request = null,
    ) {}

    public function withValue(mixed $value): self
    {
        return new self(
            $this->record,
            $this->tcaField,
            $this->targetField,
            $this->sourceField,
            $this->definition,
            $this->configuration,
            $value,
            $this->site,
            $this->languageId,
            $this->request,
        );
    }
}
