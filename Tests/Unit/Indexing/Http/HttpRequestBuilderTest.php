<?php

declare(strict_types=1);

namespace Lochmueller\Index\Tests\Unit\Indexing\Http;

use Lochmueller\Index\Indexing\Http\HttpRequestBuilder;
use Lochmueller\Index\Tests\Unit\AbstractTest;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;
use Psr\Http\Message\UriInterface;
use TYPO3\CMS\Core\Http\RequestFactory;
use TYPO3\CMS\Core\Http\Request;

class HttpRequestBuilderTest extends AbstractTest
{
    public function testBuildRequestForPageReturnsContentOnSuccess(): void
    {
        $uri = $this->createStub(UriInterface::class);

        $stream = $this->createStub(StreamInterface::class);
        $stream->method('getContents')->willReturn('<html>Test content</html>');

        $response = $this->createStub(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn(200);
        $response->method('getBody')->willReturn($stream);

        $client = $this->createStub(ClientInterface::class);
        $client->method('sendRequest')->willReturn($response);

        $request = $this->createStub(Request::class);
        $requestFactory = $this->createStub(RequestFactory::class);
        $requestFactory->method('createRequest')->willReturn($request);

        $subject = new HttpRequestBuilder($client, $requestFactory);
        $result = $subject->buildRequestForPage($uri);

        self::assertSame('<html>Test content</html>', $result);
    }

    public function testBuildRequestForPageReturnsEmptyStringOnNon200Status(): void
    {
        $uri = $this->createStub(UriInterface::class);

        $response = $this->createStub(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn(404);

        $client = $this->createStub(ClientInterface::class);
        $client->method('sendRequest')->willReturn($response);

        $request = $this->createStub(Request::class);
        $requestFactory = $this->createStub(RequestFactory::class);
        $requestFactory->method('createRequest')->willReturn($request);

        $subject = new HttpRequestBuilder($client, $requestFactory);
        $result = $subject->buildRequestForPage($uri);

        self::assertSame('', $result);
    }

    public function testBuildRequestForPageReturnsEmptyStringOnException(): void
    {
        $uri = $this->createStub(UriInterface::class);

        $client = $this->createStub(ClientInterface::class);
        $client->method('sendRequest')->willThrowException(new \Exception('Connection failed'));

        $request = $this->createStub(Request::class);
        $requestFactory = $this->createStub(RequestFactory::class);
        $requestFactory->method('createRequest')->willReturn($request);

        $subject = new HttpRequestBuilder($client, $requestFactory);
        $result = $subject->buildRequestForPage($uri);

        self::assertSame('', $result);
    }

    public function testBuildRequestForPageCreatesGetRequest(): void
    {
        $uri = $this->createStub(UriInterface::class);

        $response = $this->createStub(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn(500);

        $client = $this->createStub(ClientInterface::class);
        $client->method('sendRequest')->willReturn($response);

        $request = $this->createStub(Request::class);
        $requestFactory = $this->createMock(RequestFactory::class);
        $requestFactory->expects(self::once())
            ->method('createRequest')
            ->with('GET', $uri)
            ->willReturn($request);

        $subject = new HttpRequestBuilder($client, $requestFactory);
        $subject->buildRequestForPage($uri);
    }
}
