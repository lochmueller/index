<?php

declare(strict_types=1);

namespace Lochmueller\Index\Indexing\Database\ContentType\BootstrapPackage;

use Lochmueller\Index\Indexing\Database\DatabaseIndexingDto;
use TYPO3\CMS\Core\Domain\Record;

class BootstrapPackageSimpleContentType extends AbstractBootstrapPackageContentType
{
    private const SIMPLE_TYPES = [
        'textcolumn',
        'texticon',
        'listgroup',
        'panel',
    ];

    private const TEASER_TYPES = [
        'textteaser',
    ];

    private const QUOTE_TYPES = [
        'quote',
    ];

    public function canHandle(Record $record): bool
    {
        if (!$this->isBootstrapPackageActive()) {
            return false;
        }
        return $this->isAnyType($record, [...self::SIMPLE_TYPES, ...self::TEASER_TYPES, ...self::QUOTE_TYPES]);
    }

    public function addContent(Record $record, DatabaseIndexingDto $dto): void
    {
        $this->headerContentType->addContent($record, $dto);

        if ($this->isAnyType($record, self::TEASER_TYPES)) {
            $teaser = trim((string) $record->get('teaser'));
            if ($teaser !== '') {
                $dto->content .= '<p>' . $teaser . '</p>';
            }
        }

        if ($this->isAnyType($record, self::QUOTE_TYPES)) {
            $quoteSource = trim((string) $record->get('quote_source'));
            if ($quoteSource !== '') {
                $dto->content .= '<cite>' . $quoteSource . '</cite>';
            }
        }

        $bodytext = trim((string) $record->get('bodytext'));
        if ($bodytext !== '') {
            $dto->content .= $bodytext;
        }
    }
}
