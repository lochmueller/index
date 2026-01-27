<?php

declare(strict_types=1);

namespace Lochmueller\Index\Tests\Unit\Reaction;

use Lochmueller\Index\Indexing\External\ExternalIndexingQueue;
use Lochmueller\Index\Reaction\IndexExternalFileReaction;
use Lochmueller\Index\Tests\Unit\AbstractTest;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Message\StreamInterface;
use TYPO3\CMS\Core\Exception\SiteNotFoundException;
use TYPO3\CMS\Core\Site\Entity\Site;
use TYPO3\CMS\Core\Site\SiteFinder;
use TYPO3\CMS\Reactions\Model\ReactionInstruction;

class IndexExternalFileReactionTest extends AbstractTest
{
    public function testGetTypeReturnsCorrectIdentifier(): void
    {
        self::assertSame('index-external-file-reaction', IndexExternalFileReaction::getType());
    }

    public function testGetDescriptionReturnsNonEmptyString(): void
    {
        $description = IndexExternalFileReaction::getDescription();

        self::assertNotEmpty($description);
        self::assertStringContainsString('file', $description);
    }

    public function testGetIconIdentifierReturnsExtensionIcon(): void
    {
        self::assertSame('ext-index-icon', IndexExternalFileReaction::getIconIdentifier());
    }

    public function testReactWithValidSiteCallsFillQueueForFile(): void
    {
        $site = $this->createStub(Site::class);

        $siteFinder = $this->createStub(SiteFinder::class);
        $siteFinder->method('getSiteByIdentifier')->willReturn($site);

        $externalIndexingQueue = $this->createMock(ExternalIndexingQueue::class);
        $externalIndexingQueue->expects(self::once())
            ->method('fillQueue')
            ->with($site, 1, ['title' => 'Test File', 'content' => 'File content'], false);

        $response = $this->createStub(ResponseInterface::class);
        $response->method('withHeader')->willReturnSelf();
        $response->method('withBody')->willReturnSelf();

        $responseFactory = $this->createStub(ResponseFactoryInterface::class);
        $responseFactory->method('createResponse')->willReturn($response);

        $stream = $this->createStub(StreamInterface::class);
        $streamFactory = $this->createStub(StreamFactoryInterface::class);
        $streamFactory->method('createStream')->willReturn($stream);

        $subject = new IndexExternalFileReaction(
            $responseFactory,
            $streamFactory,
            $siteFinder,
            $externalIndexingQueue,
        );

        $request = $this->createStub(ServerRequestInterface::class);
        $reaction = $this->createStub(ReactionInstruction::class);

        $payload = [
            'meta' => [
                'siteIdentifier' => 'main-site',
                'language' => 1,
            ],
            'data' => [
                'title' => 'Test File',
                'content' => 'File content',
            ],
        ];

        $subject->react($request, $payload, $reaction);
    }

    public function testReactWithInvalidSiteReturnsErrorResponse(): void
    {
        $siteFinder = $this->createStub(SiteFinder::class);
        $siteFinder->method('getSiteByIdentifier')
            ->willThrowException(new SiteNotFoundException('Site not found'));

        $externalIndexingQueue = $this->createStub(ExternalIndexingQueue::class);

        $response = $this->createStub(ResponseInterface::class);
        $response->method('withHeader')->willReturnSelf();
        $response->method('withBody')->willReturnSelf();

        $responseFactory = $this->createMock(ResponseFactoryInterface::class);
        $responseFactory->expects(self::once())
            ->method('createResponse')
            ->with(400)
            ->willReturn($response);

        $stream = $this->createStub(StreamInterface::class);
        $streamFactory = $this->createStub(StreamFactoryInterface::class);
        $streamFactory->method('createStream')->willReturn($stream);

        $subject = new IndexExternalFileReaction(
            $responseFactory,
            $streamFactory,
            $siteFinder,
            $externalIndexingQueue,
        );

        $request = $this->createStub(ServerRequestInterface::class);
        $reaction = $this->createStub(ReactionInstruction::class);

        $payload = [
            'meta' => [
                'siteIdentifier' => 'non-existent-site',
            ],
            'data' => [],
        ];

        $subject->react($request, $payload, $reaction);
    }

    public function testReactWithMissingLanguageDefaultsToZero(): void
    {
        $site = $this->createStub(Site::class);

        $siteFinder = $this->createStub(SiteFinder::class);
        $siteFinder->method('getSiteByIdentifier')->willReturn($site);

        $externalIndexingQueue = $this->createMock(ExternalIndexingQueue::class);
        $externalIndexingQueue->expects(self::once())
            ->method('fillQueue')
            ->with($site, 0, self::anything(), false);

        $response = $this->createStub(ResponseInterface::class);
        $response->method('withHeader')->willReturnSelf();
        $response->method('withBody')->willReturnSelf();

        $responseFactory = $this->createStub(ResponseFactoryInterface::class);
        $responseFactory->method('createResponse')->willReturn($response);

        $stream = $this->createStub(StreamInterface::class);
        $streamFactory = $this->createStub(StreamFactoryInterface::class);
        $streamFactory->method('createStream')->willReturn($stream);

        $subject = new IndexExternalFileReaction(
            $responseFactory,
            $streamFactory,
            $siteFinder,
            $externalIndexingQueue,
        );

        $request = $this->createStub(ServerRequestInterface::class);
        $reaction = $this->createStub(ReactionInstruction::class);

        $payload = [
            'meta' => [
                'siteIdentifier' => 'main-site',
            ],
            'data' => [],
        ];

        $subject->react($request, $payload, $reaction);
    }
}
