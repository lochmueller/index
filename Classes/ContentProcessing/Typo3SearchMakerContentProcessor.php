<?php

declare(strict_types=1);

namespace Lochmueller\Index\ContentProcessing;

/**
 * Processes HTML content according to the indexed_search marker rules.
 *
 * Recognised markers: <!--TYPO3SEARCH_begin--> and <!--TYPO3SEARCH_end-->
 *
 * Rules:
 *  - If there is no marker at all, everything is included.
 *  - If the first found marker is an "end" marker, the previous content until that
 *    point is included and the preceding code until next "begin" marker is excluded.
 *  - If the first found marker is a "begin" marker, the previous content until that
 *    point is excluded and preceding content until next "end" marker is included.
 *  - If there are multiple marker pairs in HTML, content from in between all pairs
 *    is included.
 *
 * @see https://docs.typo3.org/c/typo3/cms-indexed-search/main/en-us/TechnicalDetails/HtmlContent/Index.html
 */
class Typo3SearchMakerContentProcessor implements ContentProcessorInterface
{
    private const MARKER_PATTERN = '/<!--TYPO3SEARCH_(begin|end)-->/';

    public function getLabel(): string
    {
        return 'LLL:EXT:index/Resources/Private/Language/locallang.xlf:tx_index_domain_model_configuration.content_processors.type.typo3_search_marker';
    }

    public function process(string $htmlContent): string
    {
        if (!preg_match_all(self::MARKER_PATTERN, $htmlContent, $matches, PREG_OFFSET_CAPTURE)) {
            return $htmlContent;
        }

        $include = $matches[1][0][0] === 'end';
        $result = '';
        $cursor = 0;

        foreach ($matches[0] as $index => $match) {
            [$markerText, $markerOffset] = $match;
            $markerType = $matches[1][$index][0];

            if ($include) {
                $result .= substr($htmlContent, $cursor, $markerOffset - $cursor);
            }

            $include = $markerType === 'begin';
            $cursor = $markerOffset + strlen($markerText);
        }

        if ($include) {
            $result .= substr($htmlContent, $cursor);
        }

        return $result;
    }
}
