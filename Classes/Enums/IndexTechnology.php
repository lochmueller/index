<?php

declare(strict_types=1);

namespace Lochmueller\Index\Enums;

enum IndexTechnology
{
    case Cache;
    case Database;
    case Web;
}
