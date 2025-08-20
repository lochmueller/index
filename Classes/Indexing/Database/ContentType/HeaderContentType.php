<?php

declare(strict_types=1);

namespace Lochmueller\Index\Indexing\Database\ContentType;

use Lochmueller\Index\Indexing\Database\DatabaseIndexingDto;
use TYPO3\CMS\Core\Domain\Record;

class HeaderContentType extends SimpleContentType
{
    public function canHandle(Record $record): bool
    {
        return $record->getRecordType() === 'header';
    }

    public function addContent(Record $record, DatabaseIndexingDto $dto): void
    {
        $layout = (int) $record->get('header_layout');
        if ($layout > 10) {
            return;
        } elseif ($layout === 0) {
            $layout = 1;
        }

        $return = '<h' . $layout . '>' . $record->get('header') . '</h' . $layout . '>';

        $subheader = trim((string) $record->get('subheader'));
        if ($subheader !== '') {
            $return .= '<p>' . $subheader . '</p>';
        }

        $dto->content .= $return;
    }
}
