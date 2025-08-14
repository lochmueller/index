<?php

declare(strict_types=1);

namespace Lochmueller\Index\Enums;

enum IndexPartialTrigger: string
{
    case Datamap = 'datamap';
    case Cmdmap = 'cmdmap';
    case Clearcache = 'clearcache';
}
