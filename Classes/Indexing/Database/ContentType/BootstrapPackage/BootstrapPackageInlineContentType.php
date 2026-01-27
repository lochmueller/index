<?php

declare(strict_types=1);

namespace Lochmueller\Index\Indexing\Database\ContentType\BootstrapPackage;

use Lochmueller\Index\Indexing\Database\ContentType\HeaderContentType;
use Lochmueller\Index\Indexing\Database\DatabaseIndexingDto;
use TYPO3\CMS\Core\Domain\Record;

class BootstrapPackageInlineContentType extends AbstractBootstrapPackageContentType
{
    private const INLINE_TYPES = [
        'accordion' => 'tx_bootstrappackage_accordion_item',
        'tab' => 'tx_bootstrappackage_tab_item',
        'card_group' => 'tx_bootstrappackage_card_group_item',
        'icon_group' => 'tx_bootstrappackage_icon_group_item',
        'timeline' => 'tx_bootstrappackage_timeline_item',
        'carousel' => 'tx_bootstrappackage_carousel_item',
        'carousel_small' => 'tx_bootstrappackage_carousel_item',
        'carousel_fullscreen' => 'tx_bootstrappackage_carousel_item',
    ];

    public function __construct(
        HeaderContentType $headerContentType,
        protected readonly InlineRelationService $inlineRelationService,
    ) {
        parent::__construct($headerContentType);
    }

    public function canHandle(Record $record): bool
    {
        if (!$this->isBootstrapPackageActive()) {
            return false;
        }
        $recordType = $record->getRecordType();
        if ($recordType === null) {
            return false;
        }
        return array_key_exists($recordType, self::INLINE_TYPES);
    }

    public function addContent(Record $record, DatabaseIndexingDto $dto): void
    {
        $this->headerContentType->addContent($record, $dto);

        $recordType = $record->getRecordType();
        if ($recordType === null || !isset(self::INLINE_TYPES[$recordType])) {
            return;
        }

        $table = self::INLINE_TYPES[$recordType];
        $languageUid = $record->getLanguageId() ?? 0;
        $parentUid = (int) $record->get('uid');

        foreach ($this->inlineRelationService->findByParent($parentUid, $table, $languageUid) as $item) {
            $this->addItemContent($item, $dto, $recordType);
        }
    }

    protected function addItemContent(Record $item, DatabaseIndexingDto $dto, string $parentType): void
    {
        $header = trim((string) $item->get('header'));
        if ($header !== '') {
            $dto->content .= '<h3>' . $header . '</h3>';
        }

        // Card group and carousel items have subheader
        if (in_array($parentType, ['card_group', 'carousel', 'carousel_small', 'carousel_fullscreen'], true)) {
            $subheader = trim((string) $item->get('subheader'));
            if ($subheader !== '') {
                $dto->content .= '<p>' . $subheader . '</p>';
            }
        }

        // Timeline items have date field
        if ($parentType === 'timeline') {
            $date = trim((string) $item->get('date'));
            if ($date !== '') {
                $dto->content .= '<time>' . $date . '</time>';
            }
        }

        $bodytext = trim((string) $item->get('bodytext'));
        if ($bodytext !== '') {
            $dto->content .= $bodytext;
        }
    }
}
