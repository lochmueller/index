<?php

declare(strict_types=1);

namespace Lochmueller\Index\Configuration;

use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use TYPO3\CMS\Core\Configuration\Loader\YamlFileLoader;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Utility\PathUtility;

/**
 * Resolves the JSON configuration of an index configuration record incl. "@import" of external files.
 *
 * Imported files are merged in the given order, the values of the importing configuration always win.
 * Associative arrays are merged recursively, lists (e.g. "extender") are replaced as a whole.
 */
class ConfigurationResolver implements LoggerAwareInterface
{
    use LoggerAwareTrait;

    public const IMPORT_KEY = '@import';

    public function __construct(
        protected readonly YamlFileLoader $yamlFileLoader,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function resolve(string $json): array
    {
        $configuration = json_decode($json, true);
        if (!is_array($configuration)) {
            return [];
        }

        return $this->resolveImports($configuration, null, []);
    }

    /**
     * @param array<mixed> $configuration
     * @param string[] $importStack
     * @return array<mixed>
     */
    protected function resolveImports(array $configuration, ?string $currentFile, array $importStack): array
    {
        $imports = $configuration[self::IMPORT_KEY] ?? [];
        unset($configuration[self::IMPORT_KEY]);

        if (is_string($imports)) {
            $imports = [$imports];
        }
        if (!is_array($imports)) {
            $this->logger?->warning('Invalid "@import" value in index configuration, string or list of strings expected');
            return $configuration;
        }

        $base = [];
        foreach ($imports as $import) {
            if (!is_string($import) || trim($import) === '') {
                $this->logger?->warning('Invalid "@import" entry in index configuration, string expected');
                continue;
            }
            $base = $this->merge($base, $this->loadFile(trim($import), $currentFile, $importStack));
        }

        return $this->merge($base, $configuration);
    }

    /**
     * @param string[] $importStack
     * @return array<mixed>
     */
    protected function loadFile(string $fileName, ?string $currentFile, array $importStack): array
    {
        $absoluteFileName = $this->getAbsoluteFileName($fileName, $currentFile);
        if ($absoluteFileName === '' || !is_file($absoluteFileName) || !is_readable($absoluteFileName)) {
            $this->logger?->warning('Index configuration import could not be loaded', ['file' => $fileName]);
            return [];
        }
        if (in_array($absoluteFileName, $importStack, true)) {
            $this->logger?->warning('Index configuration import is recursive', ['file' => $fileName, 'stack' => $importStack]);
            return [];
        }
        $importStack[] = $absoluteFileName;

        try {
            $content = match (strtolower(pathinfo($absoluteFileName, PATHINFO_EXTENSION))) {
                'yaml', 'yml' => $this->yamlFileLoader->load($absoluteFileName),
                'json' => json_decode((string) file_get_contents($absoluteFileName), true, 512, JSON_THROW_ON_ERROR),
                default => null,
            };
        } catch (\JsonException|\RuntimeException $exception) {
            $this->logger?->warning('Index configuration import could not be parsed', ['file' => $fileName, 'exception' => $exception]);
            return [];
        }

        if (!is_array($content)) {
            $this->logger?->warning('Index configuration import has an unsupported format or contains no data', ['file' => $fileName]);
            return [];
        }

        return $this->resolveImports($content, $absoluteFileName, $importStack);
    }

    protected function getAbsoluteFileName(string $fileName, ?string $currentFile): string
    {
        if ($currentFile === null || PathUtility::isExtensionPath($fileName) || PathUtility::isAbsolutePath($fileName)) {
            return GeneralUtility::getFileAbsFileName($fileName);
        }

        // Relative to the importing file
        $absoluteFileName = PathUtility::getAbsolutePathOfRelativeReferencedFileOrPath($currentFile, $fileName);

        return GeneralUtility::isAllowedAbsPath($absoluteFileName) ? $absoluteFileName : '';
    }

    /**
     * @param array<mixed> $base
     * @param array<mixed> $override
     * @return array<mixed>
     */
    protected function merge(array $base, array $override): array
    {
        foreach ($override as $key => $value) {
            if (is_array($value) && !array_is_list($value) && isset($base[$key]) && is_array($base[$key]) && !array_is_list($base[$key])) {
                $base[$key] = $this->merge($base[$key], $value);
                continue;
            }
            $base[$key] = $value;
        }

        return $base;
    }
}
