<?php

declare(strict_types=1);

namespace Lochmueller\Index\Enums;

enum IndexTechnology: string
{
    case None = 'none';
    case Cache = 'cache';
    case Database = 'database';
    case Frontend = 'frontend';
    case Http = 'http';
    case External = 'external';
    case Fetch = 'fetch';

    /**
     * Whether an index process of this technology is started from an index configuration record, which
     * is what ActiveIndexing and the IndexQueueInterface implementations do.
     *
     * The other technologies have their own entry points: Cache runs during a frontend request,
     * External is triggered by a reaction, and None disables the indexing of the configuration.
     */
    public function isTriggeredByConfiguration(): bool
    {
        return match ($this) {
            self::Database, self::Frontend, self::Http, self::Fetch => true,
            self::None, self::Cache, self::External => false,
        };
    }
}
