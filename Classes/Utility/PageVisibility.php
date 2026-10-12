<?php

declare(strict_types=1);

namespace Lochmueller\Index\Utility;

use TYPO3\CMS\Core\Domain\Repository\PageRepository;

/**
 * Checks if a page record is rendered in the frontend - independent of the
 * current context, so it works the same in the backend, in CLI and in workers.
 */
final class PageVisibility
{
    private const EXCLUDED_DOKTYPES = [
        PageRepository::DOKTYPE_SYSFOLDER,
        PageRepository::DOKTYPE_SPACER,
        PageRepository::DOKTYPE_LINK,
        PageRepository::DOKTYPE_BE_USER_SECTION,
        PageRepository::DOKTYPE_SHORTCUT,
        PageRepository::DOKTYPE_MOUNTPOINT,
    ];

    /**
     * @param array<string, mixed> $row
     */
    public static function isRenderable(array $row, int $now): bool
    {
        return !self::isExcludedDoktype($row) && self::isVisible($row, $now);
    }

    /**
     * @param array<string, mixed> $row
     */
    public static function isExcludedDoktype(array $row): bool
    {
        return isset($row['doktype']) && in_array((int) $row['doktype'], self::EXCLUDED_DOKTYPES, true);
    }

    /**
     * Hidden flag and the publishing date range (starttime/endtime).
     *
     * @param array<string, mixed> $row
     */
    public static function isVisible(array $row, int $now): bool
    {
        if ((bool) ($row['hidden'] ?? false)) {
            return false;
        }

        $starttime = (int) ($row['starttime'] ?? 0);
        if ($starttime > $now) {
            return false;
        }

        $endtime = (int) ($row['endtime'] ?? 0);

        return $endtime === 0 || $endtime > $now;
    }
}
