<?php

declare(strict_types=1);

namespace Lochmueller\Indexing\Enums;

enum IndexTechnology
{
    case Cache;
    case Database;
    case Web;
}
