<?php

declare(strict_types=1);

namespace Lochmueller\Index\Tests\Unit\Reaction;

use Lochmueller\Index\Indexing\External\ExternalIndexingQueue;
use Lochmueller\Index\Reaction\IndexExternalPageReaction;
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

class IndexExternalPageReactionTest extends AbstractTest
{
    public function testGetTypeReturnsCorrectIdentifier(): void
    {
        self::assertSame('index-external-page-reaction', IndexExternalPageReaction::getType());
    }

    public function testGetDescriptionReturnsNonEmptyString(): void
    {
        $description = IndexExternalPageReaction::getDescription();

        self::assertNotEmpty($description);
        self::assertStringContainsString('page', $description);
    }

    public function testGetIconIdentifierReturnsExtensionIcon(): void
    {
        self::assertSame('ext-index-icon', IndexExternalPageReaction::getIconIdentifier());
    }

    public function testReactWithValidSiteCallsFillQueueForPage(): void
    {
        $site = $this->createStub(Site::class);

        $siteFinder = $this->createStub(SiteFinder::class);
        $siteFinder->method('getSiteByIdentifier')->willReturn($site);

        $externalIndexingQueue = $this->createMock(ExternalIndexingQueue::class);
        $externalIndexingQueue->expects(self::once())
            ->method('fillQueue')
            ->with($site, 1, ['title' => 'Test Page', 'content' => 'Content'], true);

        $response = $this->createStub(ResponseInterface::class);
        $response->method('withHeader')->willReturnSelf();
        $response->method('withBody')->willReturnSelf();

        $responseFactory = $this->createStub(ResponseFactoryInterface::class);
        $responseFactory->method('createResponse')->willReturn($response);

        $stream = $this->createStub(StreamInterface::class);
        $streamFactory = $this->createStub(StreamFactoryInterface::class);
        $streamFactory->method('createStream')->willReturn($stream);

        $subject = new IndexExternalPageReaction(
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
                'title' => 'Test Page',
                'content' => 'Content',
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

        $subject = new IndexExternalPageReaction(
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
            ->with($site, 0, self::anything(), true);

        $response = $this->createStub(ResponseInterface::class);
        $response->method('withHeader')->willReturnSelf();
        $response->method('withBody')->willReturnSelf();

        $responseFactory = $this->createStub(ResponseFactoryInterface::class);
        $responseFactory->method('createResponse')->willReturn($response);

        $stream = $this->createStub(StreamInterface::class);
        $streamFactory = $this->createStub(StreamFactoryInterface::class);
        $streamFactory->method('createStream')->willReturn($stream);

        $subject = new IndexExternalPageReaction(
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
