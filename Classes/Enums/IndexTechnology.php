<?php

declare(strict_types=1);

namespace Lochmueller\Index\Enums;

enum IndexTechnology: string
{
    case None = 'none';
    case Cache = 'cache';
    case Database = 'database';
    case Frontend = 'frontend';
}
