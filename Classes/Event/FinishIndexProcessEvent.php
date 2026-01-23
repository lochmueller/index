<?php

declare(strict_types=1);

namespace Lochmueller\Index\Event;

use Lochmueller\Index\Enums\IndexTechnology;
use Lochmueller\Index\Enums\IndexType;
use TYPO3\CMS\Core\Site\Entity\SiteInterface;

final readonly class FinishIndexProcessEvent
{
    public function __construct(
        /** Meta information */
        public SiteInterface   $site,
        public IndexTechnology $technology,
        public IndexType       $type,
        public ?int            $indexConfigurationRecordId,
        public string          $indexProcessId,
        public float           $endTime,
    ) {}

}
