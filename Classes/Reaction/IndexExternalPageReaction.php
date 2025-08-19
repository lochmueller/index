<?php

declare(strict_types=1);

namespace Lochmueller\Index\Reaction;

use TYPO3\CMS\Reactions\Reaction\ReactionInterface;

class IndexExternalPageReaction extends AbstractIndexExternalReaction implements ReactionInterface
{
    public static function getType(): string
    {
        return 'index-external-page-reaction';
    }

    public static function getDescription(): string
    {
        return 'Trigger the internal index process for a page with the payload information of the webhook call';
    }

    protected function isPage(): bool
    {
        return true;
    }
}
