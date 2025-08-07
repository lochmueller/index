<?php

declare(strict_types=1);

namespace Lochmueller\Index\Enums;

enum IndexType: string
{
    case Full = 'full';
    case Partial = 'partial';
}
