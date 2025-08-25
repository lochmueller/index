<?php

declare(strict_types=1);

namespace Lochmueller\Index\Reaction;

use Lochmueller\Index\Indexing\External\ExternalIndexingQueue;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamFactoryInterface;
use TYPO3\CMS\Core\Exception\SiteNotFoundException;
use TYPO3\CMS\Core\Site\SiteFinder;
use TYPO3\CMS\Reactions\Model\ReactionInstruction;

abstract class AbstractIndexExternalReaction
{
    public function __construct(
        private readonly ResponseFactoryInterface $responseFactory,
        private readonly StreamFactoryInterface   $streamFactory,
        protected readonly SiteFinder             $siteFinder,
        protected readonly ExternalIndexingQueue  $externalIndexingQueue,
    ) {}

    protected function jsonResponse(array $data, int $statusCode = 201): ResponseInterface
    {
        return $this->responseFactory
            ->createResponse($statusCode)
            ->withHeader('Content-Type', 'application/json')
            ->withBody($this->streamFactory->createStream(json_encode($data, JSON_THROW_ON_ERROR)));
    }

    public static function getIconIdentifier(): string
    {
        return 'ext-index-icon';
    }

    abstract protected function isPage(): bool;

    public function react(ServerRequestInterface $request, array $payload, ReactionInstruction $reaction): ResponseInterface
    {
        $siteIdentifier = $payload['meta']['siteIdentifier'] ?? '';
        try {
            $site = $this->siteFinder->getSiteByIdentifier($siteIdentifier);
        } catch (SiteNotFoundException $e) {
            $data = [
                'success' => false,
                'error' => 'Site not found',
            ];
            return $this->jsonResponse($data, 400);
        }

        $language = (int) ($payload['meta']['language'] ?? 0);

        $this->externalIndexingQueue->fillQueue($site, $language, $payload['data'], $this->isPage());

        return $this->jsonResponse([
            'success' => true,
        ]);
    }
}
