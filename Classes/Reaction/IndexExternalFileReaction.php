<?php

declare(strict_types=1);

namespace Lochmueller\Index\Reaction;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Reactions\Model\ReactionInstruction;
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

    public function react(ServerRequestInterface $request, array $payload, ReactionInstruction $reaction): ResponseInterface
    {
        // @todo implement
        $data = [
            'success' => false,
            'error' => 'not implemented yet',
        ];

        return $this->jsonResponse($data, 400);
    }
}
