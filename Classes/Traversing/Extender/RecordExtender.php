<?php

declare(strict_types=1);

namespace Lochmueller\Index\Traversing\Extender;

use Lochmueller\Index\Configuration\Configuration;
use Lochmueller\Index\Traversing\FrontendInformationDto;
use Lochmueller\Index\Traversing\RecordSelection;
use TYPO3\CMS\Core\Domain\Record;
use TYPO3\CMS\Core\Routing\PageRouter;
use TYPO3\CMS\Core\Site\Entity\Site;
use TYPO3\CMS\Core\Site\Entity\SiteLanguage;

/**
 * Generic configuration based extender. Every detail view that is built out of a TCA table and a plugin on a
 * detail page can be handled without a dedicated PHP class. The table, the optional record filter and the
 * routing arguments are part of the index configuration (JSON).
 *
 * Extend this class and override getBaseConfiguration() if you want to ship a named preset for your own
 * extension instead of repeating the configuration in every index configuration record.
 */
class RecordExtender implements ExtenderInterface
{
    public function __construct(
        private readonly RecordSelection $recordSelection,
    ) {}

    public function getName(): string
    {
        return 'record';
    }

    /**
     * @param array<string, mixed> $extenderConfiguration
     * @param array<string, mixed> $row
     * @return iterable<FrontendInformationDto>
     */
    public function getItems(
        Configuration $configuration,
        array $extenderConfiguration,
        Site $site,
        int $pageUid,
        SiteLanguage $siteLanguage,
        array $row,
    ): iterable {
        $extenderConfiguration = array_replace($this->getBaseConfiguration(), $extenderConfiguration);

        $table = is_string($extenderConfiguration['table'] ?? null) ? trim($extenderConfiguration['table']) : '';
        if ($table === '') {
            return;
        }

        $recordTypes = $this->getRecordTypes($extenderConfiguration);
        $constraints = is_array($extenderConfiguration['constraints'] ?? null) ? $extenderConfiguration['constraints'] : [];
        $argumentConfiguration = is_array($extenderConfiguration['arguments'] ?? null) ? $extenderConfiguration['arguments'] : [];

        /** @var PageRouter $router */
        $router = $site->getRouter();

        $records = $this->recordSelection->findRecordsOnPage(
            $table,
            $this->getRecordStorages($extenderConfiguration, $pageUid),
            $siteLanguage->getLanguageId(),
        );

        foreach ($records as $record) {
            if ($recordTypes !== null && !in_array((string) $record->getRecordType(), $recordTypes, true)) {
                continue;
            }
            if (!$this->matchesConstraints($record, $constraints)) {
                continue;
            }

            $arguments = ['_language' => $siteLanguage] + $this->resolveArguments($argumentConfiguration, $record, $pageUid, $siteLanguage);

            yield new FrontendInformationDto(
                uri: $router->generateUri($pageUid, $arguments),
                arguments: $arguments,
                pageUid: $pageUid,
                language: $siteLanguage,
                row: $row,
            );
        }
    }

    /**
     * Default configuration of the extender. Empty for the generic extender, but a good extension point for
     * own presets that are based on this class.
     *
     * @return array<string, mixed>
     */
    protected function getBaseConfiguration(): array
    {
        return [];
    }

    /**
     * @param array<string, mixed> $extenderConfiguration
     * @return int[]
     */
    protected function getRecordStorages(array $extenderConfiguration, int $pageUid): array
    {
        $recordStorages = $extenderConfiguration['recordStorages'] ?? null;
        if (!is_array($recordStorages) || $recordStorages === []) {
            // Fallback: the records are stored on the detail page itself
            return [$pageUid];
        }

        return array_values(array_map(static fn($recordStorage): int => (int) $recordStorage, $recordStorages));
    }

    /**
     * @param array<string, mixed> $extenderConfiguration
     * @return string[]|null
     */
    protected function getRecordTypes(array $extenderConfiguration): ?array
    {
        $recordTypes = $extenderConfiguration['recordTypes'] ?? null;
        if (!is_array($recordTypes) || $recordTypes === []) {
            return null;
        }

        return array_values(array_map(static fn($recordType): string => (string) $recordType, $recordTypes));
    }

    /**
     * @param array<string|int, mixed> $constraints
     */
    protected function matchesConstraints(Record $record, array $constraints): bool
    {
        foreach ($constraints as $field => $expected) {
            if (!is_string($field) || !$record->has($field)) {
                return false;
            }
            if (!$this->matchesValue($record->get($field), $expected)) {
                return false;
            }
        }

        return true;
    }

    protected function matchesValue(mixed $value, mixed $expected): bool
    {
        if (is_array($expected)) {
            foreach ($expected as $expectedItem) {
                if ($this->matchesValue($value, $expectedItem)) {
                    return true;
                }
            }

            return false;
        }

        $value = $this->toComparableString($value);
        $expected = $this->toComparableString($expected);

        return $value !== null && $expected !== null && $value === $expected;
    }

    /**
     * @param array<string|int, mixed> $arguments
     * @return array<string, mixed>
     */
    protected function resolveArguments(array $arguments, Record $record, int $pageUid, SiteLanguage $siteLanguage): array
    {
        $resolved = [];
        foreach ($arguments as $key => $value) {
            $key = (string) $key;
            if (is_array($value)) {
                $resolved[$key] = $this->resolveArguments($value, $record, $pageUid, $siteLanguage);
            } elseif (is_string($value)) {
                $resolved[$key] = $this->resolveValue($value, $record, $pageUid, $siteLanguage);
            } else {
                $resolved[$key] = $value;
            }
        }

        return $resolved;
    }

    protected function resolveValue(string $value, Record $record, int $pageUid, SiteLanguage $siteLanguage): mixed
    {
        // A single placeholder keeps the original type (important for the route enhancer aspects)
        if (preg_match('/^{([^{}]+)}$/', $value, $matches) === 1) {
            return $this->resolvePlaceholder($matches[1], $record, $pageUid, $siteLanguage);
        }

        return (string) preg_replace_callback(
            '/{([^{}]+)}/',
            function (array $matches) use ($record, $pageUid, $siteLanguage): string {
                $replacement = $this->resolvePlaceholder($matches[1], $record, $pageUid, $siteLanguage);

                return $this->toComparableString($replacement) ?? $matches[0];
            },
            $value,
        );
    }

    protected function resolvePlaceholder(string $placeholder, Record $record, int $pageUid, SiteLanguage $siteLanguage): mixed
    {
        if (str_starts_with($placeholder, 'field:')) {
            $field = substr($placeholder, 6);

            return $record->has($field) ? $record->get($field) : null;
        }

        return match ($placeholder) {
            'uid' => $record->getUid(),
            'pid' => $record->getPid(),
            'pageUid' => $pageUid,
            'languageId' => $siteLanguage->getLanguageId(),
            // Unknown placeholders stay untouched, so that broken configurations are visible in the URI
            default => '{' . $placeholder . '}',
        };
    }

    protected function toComparableString(mixed $value): ?string
    {
        if ($value === null) {
            return '';
        }
        if (is_bool($value)) {
            return $value ? '1' : '0';
        }
        if (is_scalar($value) || $value instanceof \Stringable) {
            return (string) $value;
        }

        return null;
    }

}
