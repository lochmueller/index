<?php

declare(strict_types=1);

namespace Lochmueller\Index\Reaction;

use Lochmueller\Index\Enums\IndexTechnology;
use Lochmueller\Index\Enums\IndexType;
use Lochmueller\Index\Queue\Message\FinishProcessMessage;
use Lochmueller\Index\Queue\Message\FrontendIndexMessage;
use Lochmueller\Index\Queue\Message\StartProcessMessage;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Reactions\Model\ReactionInstruction;
use TYPO3\CMS\Reactions\Reaction\ReactionInterface;

class IndexExternalPageReaction extends AbstractIndexExternalReaction implements ReactionInterface
{
    public static function getType(): string
    {
        return 'index-external-file-reaction';
    }

    public static function getDescription(): string
    {
        return 'Trigger the internal index process for a page with the payload information of the webhook call';
    }


    public function react(ServerRequestInterface $request, array $payload, ReactionInstruction $reaction): ResponseInterface
    {
        $data = [
            'success' => false,
            'error' => 'not implemented yet',
        ];

        return $this->jsonResponse($data, 400);


        // @todo get site for indexing
        $site = $this->siteFinder->getSiteByPageId($configuration->pageId);

        $id = uniqid('external-index', true);
        $this->bus->dispatch(new StartProcessMessage(
            siteIdentifier: $site->getIdentifier(),
            technology: IndexTechnology::External,
            type: IndexType::Partial,
            indexConfigurationRecordId: null,
            indexProcessId: $id,
        ));


        // @todo external
        $this->bus->dispatch(new FrontendIndexMessage(
            siteIdentifier: $site->getIdentifier(),
            technology: IndexTechnology::Frontend,
            type: IndexType::Full,
            indexConfigurationRecordId: $configuration->configurationId,
            uri: $info['uri'],
            pageUid: $info['pageUid'],
            indexProcessId: $id,
        ));

        $this->bus->dispatch(new FinishProcessMessage(
            siteIdentifier: $site->getIdentifier(),
            technology: IndexTechnology::External,
            type: IndexType::Partial,
            indexConfigurationRecordId: null,
            indexProcessId: $id,
        ));
    }
}
