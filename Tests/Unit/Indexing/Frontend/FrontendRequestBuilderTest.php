<?php

declare(strict_types=1);

namespace Lochmueller\Index\Tests\Unit\Indexing\Frontend;

use Lochmueller\Index\Indexing\Frontend\FrontendContextBuilder;
use Lochmueller\Index\Indexing\Frontend\FrontendRequestBuilder;
use Lochmueller\Index\Tests\Unit\AbstractTest;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamInterface;
use Psr\Http\Message\UriInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Log\LoggerInterface;
use TYPO3\CMS\Core\Http\MiddlewareDispatcher;
use TYPO3\CMS\Frontend\Http\Application;

class FrontendRequestBuilderTest extends AbstractTest
{
    public function testBuildRequestForPageReturnsContentOnSuccess(): void
    {
        $uri = self::createStub(UriInterface::class);
        $uri->method('getHost')->willReturn('example.com');
        $uri->method('getScheme')->willReturn('https');
        $uri->method('__toString')->willReturn('https://example.com/page');

        $stream = self::createStub(StreamInterface::class);
        $stream->method('getContents')->willReturn('<html><body>Test Content</body></html>');

        $response = self::createStub(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn(200);
        $response->method('getBody')->willReturn($stream);

        $frontendContextBuilder = self::createStub(FrontendContextBuilder::class);
        $frontendContextBuilder->method('executeInFrontendContext')
            ->willReturnCallback(fn(callable $callback) => $response);

        $application = self::createStub(Application::class);

        $subject = new FrontendRequestBuilder($application, $frontendContextBuilder);

        $result = $subject->buildRequestForPage($uri);

        self::assertSame('<html><body>Test Content</body></html>', $result);
    }

    public function testBuildRequestForPageReturnsNullOnException(): void
    {
        $uri = self::createStub(UriInterface::class);
        $uri->method('getHost')->willReturn('example.com');
        $uri->method('getScheme')->willReturn('https');
        $uri->method('__toString')->willReturn('https://example.com/page');

        $frontendContextBuilder = self::createStub(FrontendContextBuilder::class);
        $frontendContextBuilder->method('executeInFrontendContext')
            ->willThrowException(new \Exception('Frontend error'));

        $application = self::createStub(Application::class);

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())
            ->method('error')
            ->with(self::stringContains('cannot fetch url'));

        $subject = new FrontendRequestBuilder($application, $frontendContextBuilder);
        $subject->setLogger($logger);

        $result = $subject->buildRequestForPage($uri);

        self::assertNull($result);
    }

    public function testBuildRequestForPageReturnsNullForErrorPages(): void
    {
        $uri = self::createStub(UriInterface::class);
        $uri->method('getHost')->willReturn('example.com');
        $uri->method('getScheme')->willReturn('https');
        $uri->method('__toString')->willReturn('https://example.com/missing');

        $stream = self::createStub(StreamInterface::class);
        $stream->method('getContents')->willReturn('<html><body>Page not found</body></html>');

        $response = self::createStub(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn(404);
        $response->method('getBody')->willReturn($stream);

        $frontendContextBuilder = self::createStub(FrontendContextBuilder::class);
        $frontendContextBuilder->method('executeInFrontendContext')
            ->willReturnCallback(fn(callable $callback) => $response);

        $subject = new FrontendRequestBuilder(self::createStub(Application::class), $frontendContextBuilder);

        self::assertNull($subject->buildRequestForPage($uri));
    }

    public function testBuildRequestForPageHandlesHttpScheme(): void
    {
        $uri = self::createStub(UriInterface::class);
        $uri->method('getHost')->willReturn('example.com');
        $uri->method('getScheme')->willReturn('http');
        $uri->method('__toString')->willReturn('http://example.com/page');

        $stream = self::createStub(StreamInterface::class);
        $stream->method('getContents')->willReturn('content');

        $response = self::createStub(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn(200);
        $response->method('getBody')->willReturn($stream);

        $frontendContextBuilder = self::createStub(FrontendContextBuilder::class);
        $frontendContextBuilder->method('executeInFrontendContext')
            ->willReturnCallback(fn(callable $callback) => $response);

        $application = self::createStub(Application::class);

        $subject = new FrontendRequestBuilder($application, $frontendContextBuilder);

        $result = $subject->buildRequestForPage($uri);

        self::assertSame('content', $result);
    }

    public function testBuildRequestForPageHandlesUriWithQueryParams(): void
    {
        $uri = self::createStub(UriInterface::class);
        $uri->method('getHost')->willReturn('example.com');
        $uri->method('getScheme')->willReturn('https');
        $uri->method('__toString')->willReturn('https://example.com/page?foo=bar&baz=qux');

        $stream = self::createStub(StreamInterface::class);
        $stream->method('getContents')->willReturn('query content');

        $response = self::createStub(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn(200);
        $response->method('getBody')->willReturn($stream);

        $frontendContextBuilder = self::createStub(FrontendContextBuilder::class);
        $frontendContextBuilder->method('executeInFrontendContext')
            ->willReturnCallback(fn(callable $callback) => $response);

        $application = self::createStub(Application::class);

        $subject = new FrontendRequestBuilder($application, $frontendContextBuilder);

        $result = $subject->buildRequestForPage($uri);

        self::assertSame('query content', $result);
    }

    public function testBuildRequestForPageLogsErrorWithoutLogger(): void
    {
        $uri = self::createStub(UriInterface::class);
        $uri->method('getHost')->willReturn('example.com');
        $uri->method('getScheme')->willReturn('https');
        $uri->method('__toString')->willReturn('https://example.com/page');

        $frontendContextBuilder = self::createStub(FrontendContextBuilder::class);
        $frontendContextBuilder->method('executeInFrontendContext')
            ->willThrowException(new \Exception('Error'));

        $application = self::createStub(Application::class);

        $subject = new FrontendRequestBuilder($application, $frontendContextBuilder);
        // No logger set - should not throw

        $result = $subject->buildRequestForPage($uri);

        self::assertNull($result);
    }

    public function testExecuteWithFrontendRequestRunsCallbackAfterMiddleware(): void
    {
        $uri = self::createStub(UriInterface::class);
        $uri->method('getHost')->willReturn('example.com');
        $uri->method('getScheme')->willReturn('https');
        $uri->method('__toString')->willReturn('https://example.com/page');

        $frontendContextBuilder = self::createStub(FrontendContextBuilder::class);
        $frontendContextBuilder->method('executeInFrontendContext')
            ->willReturnCallback(static fn(callable $callback): mixed => $callback());

        $subject = new class ($this->createStub(Application::class), $frontendContextBuilder) extends FrontendRequestBuilder {
            protected function buildDispatcher(?RequestHandlerInterface $requestHandler = null): MiddlewareDispatcher
            {
                $middleware = new class implements MiddlewareInterface {
                    public function process(
                        ServerRequestInterface $request,
                        RequestHandlerInterface $handler,
                    ): ResponseInterface {
                        return $handler->handle($request->withAttribute('frontend.typoscript', 'ready'));
                    }
                };

                if ($requestHandler === null) {
                    throw new \LogicException('The callback handler must be provided.');
                }

                return new MiddlewareDispatcher($requestHandler, [$middleware]);
            }
        };

        $result = $subject->executeWithFrontendRequest(
            $uri,
            static fn(ServerRequestInterface $request): mixed => $request->getAttribute('frontend.typoscript'),
        );

        self::assertSame('ready', $result);
    }
}
