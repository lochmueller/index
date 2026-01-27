<?php

declare(strict_types=1);

namespace Lochmueller\Index\Tests\Unit\Indexing\Frontend;

use Lochmueller\Index\Indexing\Frontend\FrontendContextBuilder;
use Lochmueller\Index\Indexing\Frontend\FrontendRequestBuilder;
use Lochmueller\Index\Tests\Unit\AbstractTest;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;
use Psr\Http\Message\UriInterface;
use Psr\Log\LoggerInterface;
use TYPO3\CMS\Frontend\Http\Application;

class FrontendRequestBuilderTest extends AbstractTest
{
    public function testBuildRequestForPageReturnsContentOnSuccess(): void
    {
        $uri = $this->createStub(UriInterface::class);
        $uri->method('getHost')->willReturn('example.com');
        $uri->method('getScheme')->willReturn('https');
        $uri->method('__toString')->willReturn('https://example.com/page');

        $stream = $this->createStub(StreamInterface::class);
        $stream->method('getContents')->willReturn('<html><body>Test Content</body></html>');

        $response = $this->createStub(ResponseInterface::class);
        $response->method('getBody')->willReturn($stream);

        $frontendContextBuilder = $this->createStub(FrontendContextBuilder::class);
        $frontendContextBuilder->method('executeInFrontendContext')
            ->willReturnCallback(fn(callable $callback) => $response);

        $application = $this->createStub(Application::class);

        $subject = new FrontendRequestBuilder($application, $frontendContextBuilder);

        $result = $subject->buildRequestForPage($uri);

        self::assertSame('<html><body>Test Content</body></html>', $result);
    }

    public function testBuildRequestForPageReturnsNullOnException(): void
    {
        $uri = $this->createStub(UriInterface::class);
        $uri->method('getHost')->willReturn('example.com');
        $uri->method('getScheme')->willReturn('https');
        $uri->method('__toString')->willReturn('https://example.com/page');

        $frontendContextBuilder = $this->createStub(FrontendContextBuilder::class);
        $frontendContextBuilder->method('executeInFrontendContext')
            ->willThrowException(new \Exception('Frontend error'));

        $application = $this->createStub(Application::class);

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())
            ->method('error')
            ->with(self::stringContains('cannot fetch url'));

        $subject = new FrontendRequestBuilder($application, $frontendContextBuilder);
        $subject->setLogger($logger);

        $result = $subject->buildRequestForPage($uri);

        self::assertNull($result);
    }

    public function testBuildRequestForPageHandlesHttpScheme(): void
    {
        $uri = $this->createStub(UriInterface::class);
        $uri->method('getHost')->willReturn('example.com');
        $uri->method('getScheme')->willReturn('http');
        $uri->method('__toString')->willReturn('http://example.com/page');

        $stream = $this->createStub(StreamInterface::class);
        $stream->method('getContents')->willReturn('content');

        $response = $this->createStub(ResponseInterface::class);
        $response->method('getBody')->willReturn($stream);

        $frontendContextBuilder = $this->createStub(FrontendContextBuilder::class);
        $frontendContextBuilder->method('executeInFrontendContext')
            ->willReturnCallback(fn(callable $callback) => $response);

        $application = $this->createStub(Application::class);

        $subject = new FrontendRequestBuilder($application, $frontendContextBuilder);

        $result = $subject->buildRequestForPage($uri);

        self::assertSame('content', $result);
    }

    public function testBuildRequestForPageHandlesUriWithQueryParams(): void
    {
        $uri = $this->createStub(UriInterface::class);
        $uri->method('getHost')->willReturn('example.com');
        $uri->method('getScheme')->willReturn('https');
        $uri->method('__toString')->willReturn('https://example.com/page?foo=bar&baz=qux');

        $stream = $this->createStub(StreamInterface::class);
        $stream->method('getContents')->willReturn('query content');

        $response = $this->createStub(ResponseInterface::class);
        $response->method('getBody')->willReturn($stream);

        $frontendContextBuilder = $this->createStub(FrontendContextBuilder::class);
        $frontendContextBuilder->method('executeInFrontendContext')
            ->willReturnCallback(fn(callable $callback) => $response);

        $application = $this->createStub(Application::class);

        $subject = new FrontendRequestBuilder($application, $frontendContextBuilder);

        $result = $subject->buildRequestForPage($uri);

        self::assertSame('query content', $result);
    }

    public function testBuildRequestForPageLogsErrorWithoutLogger(): void
    {
        $uri = $this->createStub(UriInterface::class);
        $uri->method('getHost')->willReturn('example.com');
        $uri->method('getScheme')->willReturn('https');
        $uri->method('__toString')->willReturn('https://example.com/page');

        $frontendContextBuilder = $this->createStub(FrontendContextBuilder::class);
        $frontendContextBuilder->method('executeInFrontendContext')
            ->willThrowException(new \Exception('Error'));

        $application = $this->createStub(Application::class);

        $subject = new FrontendRequestBuilder($application, $frontendContextBuilder);
        // No logger set - should not throw

        $result = $subject->buildRequestForPage($uri);

        self::assertNull($result);
    }
}
