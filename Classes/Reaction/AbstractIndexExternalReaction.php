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
    protected const MAX_URI_LENGTH = 2048;
    protected const MAX_TITLE_LENGTH = 512;
    protected const MAX_CONTENT_LENGTH = 2097152;

    public function __construct(
        private readonly ResponseFactoryInterface $responseFactory,
        private readonly StreamFactoryInterface   $streamFactory,
        protected readonly SiteFinder             $siteFinder,
        protected readonly ExternalIndexingQueue  $externalIndexingQueue,
    ) {}

    /**
     * @param array<string, mixed> $data
     */
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

    /**
     * @param array<string, mixed> $payload
     */
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

        $this->externalIndexingQueue->fillQueue($site, $language, $this->normalizeData($payload), $this->isPage());

        return $this->jsonResponse([
            'success' => true,
        ]);
    }

    /**
     * @param array<string, mixed> $payload
     * @return array{uri: string, title: string, content: string, accessGroups: int[]}
     */
    private function normalizeData(array $payload): array
    {
        $data = is_array($payload['data'] ?? null) ? $payload['data'] : [];
        $accessGroups = is_array($data['accessGroups'] ?? null) ? $data['accessGroups'] : [];

        return [
            'uri' => mb_substr(trim($this->getStringValue($data, 'uri')), 0, self::MAX_URI_LENGTH),
            'title' => mb_substr($this->getStringValue($data, 'title'), 0, self::MAX_TITLE_LENGTH),
            'content' => mb_substr($this->getStringValue($data, 'content'), 0, self::MAX_CONTENT_LENGTH),
            'accessGroups' => array_values(array_map(
                static fn(mixed $accessGroup): int => (int) $accessGroup,
                array_filter($accessGroups, static fn(mixed $accessGroup): bool => is_numeric($accessGroup)),
            )),
        ];
    }

    /**
     * @param array<string, mixed> $data
     */
    private function getStringValue(array $data, string $key): string
    {
        $value = $data[$key] ?? null;

        return is_string($value) ? $value : '';
    }
}
