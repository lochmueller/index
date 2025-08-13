<?php

declare(strict_types=1);

namespace Lochmueller\Index\Reaction;

use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Symfony\Component\Messenger\MessageBusInterface;

abstract class AbstractIndexExternalReaction
{
    public function __construct(
        protected readonly MessageBusInterface    $bus,
        private readonly ResponseFactoryInterface $responseFactory,
        private readonly StreamFactoryInterface   $streamFactory,
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
}
