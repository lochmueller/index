<?php

declare(strict_types=1);

namespace Lochmueller\Index\Tests\Unit\Utility;

use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Lochmueller\Index\Tests\Unit\AbstractTest;
use Lochmueller\Index\Utility\FetchUtility;
use Psr\Http\Client\ClientInterface;
use TYPO3\CMS\Core\Http\RequestFactory;

class FetchUtilityTest extends AbstractTest
{
    private function createSubject(?ClientInterface $client = null): FetchUtility
    {
        $requestFactory = $this->createStub(RequestFactory::class);
        $requestFactory->method('createRequest')
            ->willReturnCallback(fn(string $method, string $uri): Request => new Request($method, $uri));

        return new FetchUtility($client ?? $this->createStub(ClientInterface::class), $requestFactory);
    }

    public function testDownloadReturnsResponseDto(): void
    {
        $client = $this->createStub(ClientInterface::class);
        $client->method('sendRequest')->willReturn(new Response(200, ['Content-Type' => 'text/html; charset=UTF-8'], '<html></html>'));

        $result = $this->createSubject($client)->download('https://example.com/');

        self::assertNotNull($result);
        self::assertSame('https://example.com/', $result->uri);
        self::assertSame('text/html; charset=utf-8', $result->contentType);
        self::assertSame('<html></html>', $result->content);
        self::assertTrue($result->isHtml());
    }

    public function testDownloadReturnsNullOnNon200Status(): void
    {
        $client = $this->createStub(ClientInterface::class);
        $client->method('sendRequest')->willReturn(new Response(404));

        self::assertNull($this->createSubject($client)->download('https://example.com/missing'));
    }

    public function testDownloadReturnsNullOnException(): void
    {
        $client = $this->createStub(ClientInterface::class);
        $client->method('sendRequest')->willThrowException(new \RuntimeException('Network error'));

        self::assertNull($this->createSubject($client)->download('https://example.com/'));
    }

    public function testResolveUrl(): void
    {
        $subject = $this->createSubject();
        $base = 'https://example.com/docs/page.html?x=1';

        self::assertSame('https://example.com/docs/other.html', $subject->resolveUrl('other.html', $base));
        self::assertSame('https://example.com/root.html', $subject->resolveUrl('/root.html', $base));
        self::assertSame('https://example.com/parent.html', $subject->resolveUrl('../parent.html', $base));
        self::assertSame('https://cdn.example.com/a.js', $subject->resolveUrl('//cdn.example.com/a.js', $base));
        self::assertSame('https://other.com/', $subject->resolveUrl('https://other.com/', $base));
        self::assertSame('https://example.com/docs/page.html?y=2', $subject->resolveUrl('?y=2', $base));
        self::assertSame('#anchor', $subject->resolveUrl('#anchor', $base));
        self::assertSame('mailto:info@example.com', $subject->resolveUrl('mailto:info@example.com', $base));
        self::assertSame('javascript:void(0)', $subject->resolveUrl('javascript:void(0)', $base));
    }

    public function testMakeUrlsAbsolute(): void
    {
        $html = '<a href="sub/page.html">A</a><img src=\'/img.png\' srcset="a.png 1x, /b.png 2x"><form action="send"></form><a data-href="keep" href="#top">B</a>';

        $result = $this->createSubject()->makeUrlsAbsolute($html, 'https://example.com/docs/index.html');

        self::assertStringContainsString('href="https://example.com/docs/sub/page.html"', $result);
        self::assertStringContainsString('src=\'https://example.com/img.png\'', $result);
        self::assertStringContainsString('srcset="https://example.com/docs/a.png 1x, https://example.com/b.png 2x"', $result);
        self::assertStringContainsString('action="https://example.com/docs/send"', $result);
        self::assertStringContainsString('data-href="keep"', $result);
        self::assertStringContainsString('href="#top"', $result);
    }

    public function testMakeUrlsAbsoluteRespectsBaseTagAndEntities(): void
    {
        $html = '<head><base href="https://example.com/base/"></head><a href="page.html?a=1&amp;b=2">A</a>';

        $result = $this->createSubject()->makeUrlsAbsolute($html, 'https://example.com/other/index.html');

        self::assertStringContainsString('href="https://example.com/base/page.html?a=1&amp;b=2"', $result);
    }

    public function testExtractLinks(): void
    {
        $html = '<a href="/a.html#section">A</a><a class="x" href=\'b.html\'>B</a><a href="/a.html">A2</a>'
            . '<a href="mailto:info@example.com">Mail</a><a href="#top">Top</a><link href="/style.css">';

        $result = $this->createSubject()->extractLinks($html, 'https://example.com/docs/');

        self::assertSame(['https://example.com/a.html', 'https://example.com/docs/b.html'], $result);
    }

    public function testExtractTitle(): void
    {
        $subject = $this->createSubject();

        self::assertSame('Tom & Jerry', $subject->extractTitle('<html><title> Tom &amp; Jerry </title></html>'));
        self::assertSame('', $subject->extractTitle('<html><body></body></html>'));
    }

    public function testGetUrlBase(): void
    {
        $subject = $this->createSubject();

        self::assertSame('https://example.com/', $subject->getUrlBase('https://example.com'));
        self::assertSame('https://example.com/', $subject->getUrlBase('https://example.com/index.html'));
        self::assertSame('https://example.com/docs/', $subject->getUrlBase('https://example.com/docs'));
        self::assertSame('https://example.com/docs/', $subject->getUrlBase('https://example.com/docs/'));
        self::assertSame('https://example.com:8080/docs/', $subject->getUrlBase('https://example.com:8080/docs/start.php?x=1#y'));
    }

    public function testHasSameUrlBase(): void
    {
        $subject = $this->createSubject();
        $base = 'https://example.com/docs/';

        self::assertTrue($subject->hasSameUrlBase('https://example.com/docs/page.html', $base));
        self::assertTrue($subject->hasSameUrlBase('https://EXAMPLE.com/docs/sub/', $base));
        self::assertTrue($subject->hasSameUrlBase('https://example.com/docs', $base));
        self::assertFalse($subject->hasSameUrlBase('https://example.com/other/', $base));
        self::assertFalse($subject->hasSameUrlBase('http://example.com/docs/page.html', $base));
        self::assertFalse($subject->hasSameUrlBase('https://sub.example.com/docs/page.html', $base));
        self::assertFalse($subject->hasSameUrlBase('https://example.com:8080/docs/page.html', $base));
        self::assertTrue($subject->hasSameUrlBase('https://example.com/', 'https://example.com/'));
    }

    public function testRemoveFragment(): void
    {
        $subject = $this->createSubject();

        self::assertSame('https://example.com/a', $subject->removeFragment('https://example.com/a#b'));
        self::assertSame('https://example.com/a', $subject->removeFragment('https://example.com/a'));
    }
}
