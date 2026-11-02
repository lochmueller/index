<?php

declare(strict_types=1);

namespace Lochmueller\Index\Indexing;

use Lochmueller\Index\Configuration\Configuration;
use Lochmueller\Index\Enums\IndexTechnology;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

/**
 * Fills the message queue for one index technology of an index configuration record.
 *
 * Every implementation is picked up by ActiveIndexing via the "index.technology" tag, so an own
 * technology needs a case in the IndexTechnology enum and a service that implements this interface -
 * no core class has to be touched. Of two services for the same technology the one with the higher tag
 * priority wins, which is how a shipped technology can be replaced.
 *
 * The technologies that are not triggered by a configuration - Cache is triggered by a frontend
 * request, External by a reaction - have their own entry points and are not part of this interface.
 */
#[AutoconfigureTag(name: 'index.technology')]
interface IndexQueueInterface
{
    public function getTechnology(): IndexTechnology;

    public function fillQueue(Configuration $configuration, bool $skipFiles = false): void;

}
