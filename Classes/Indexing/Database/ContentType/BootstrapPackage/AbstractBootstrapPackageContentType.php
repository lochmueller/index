<?php

declare(strict_types=1);

namespace Lochmueller\Index\Indexing\Database\ContentType\BootstrapPackage;

use Lochmueller\Index\Indexing\Database\ContentType\HeaderContentType;
use Lochmueller\Index\Indexing\Database\ContentType\SimpleContentType;
use TYPO3\CMS\Core\Domain\Record;
use TYPO3\CMS\Core\Package\PackageManager;
use TYPO3\CMS\Core\Utility\GeneralUtility;

abstract class AbstractBootstrapPackageContentType extends SimpleContentType
{
    private static ?bool $bootstrapPackageActive = null;

    public function __construct(
        protected readonly HeaderContentType $headerContentType,
    ) {}

    protected function isBootstrapPackageActive(): bool
    {
        if (self::$bootstrapPackageActive === null) {
            $packageManager = GeneralUtility::makeInstance(PackageManager::class);
            self::$bootstrapPackageActive = $packageManager->isPackageActive('bootstrap_package');
        }
        return self::$bootstrapPackageActive;
    }

    protected function isType(Record $record, string $type): bool
    {
        return $record->getRecordType() === $type;
    }

    /**
     * @param array<string> $types
     */
    protected function isAnyType(Record $record, array $types): bool
    {
        return in_array($record->getRecordType(), $types, true);
    }

    /**
     * Reset the static cache for testing purposes.
     */
    public static function resetBootstrapPackageActiveCache(): void
    {
        self::$bootstrapPackageActive = null;
    }
}
