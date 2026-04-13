<?php

declare(strict_types=1);

namespace Lochmueller\Index\Utility;

/**
 * Utility class for parsing and formatting TYPO3 fe_group values.
 */
final class AccessGroupParser
{
    /**
     * Parses a fe_group string into an integer array.
     *
     * @return int[]
     */
    public static function parse(string $feGroup): array
    {
        $feGroup = trim($feGroup);
        if ($feGroup === '' || $feGroup === '0') {
            return [];
        }

        $groups = array_map(
            static fn(string $group): int => (int) trim($group),
            explode(',', $feGroup),
        );

        return array_values(array_filter(
            $groups,
            static fn(int $group): bool => $group !== 0,
        ));
    }

    /**
     * Formats an access groups array back into a fe_group string.
     *
     * @param int[] $accessGroups
     */
    public static function format(array $accessGroups): string
    {
        if ($accessGroups === []) {
            return '';
        }

        return implode(',', $accessGroups);
    }
}
