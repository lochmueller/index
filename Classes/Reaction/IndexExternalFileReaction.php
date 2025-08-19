<?php

declare(strict_types=1);

namespace Lochmueller\Index\Reaction;

use TYPO3\CMS\Reactions\Reaction\ReactionInterface;

class IndexExternalFileReaction extends AbstractIndexExternalReaction implements ReactionInterface
{
    public static function getType(): string
    {
        return 'index-external-file-reaction';
    }

    public static function getDescription(): string
    {
        return 'Trigger the internal index process for a file with the payload information of the webhook call';
    }

    protected function isPage(): bool
    {
        return false;
    }
}
