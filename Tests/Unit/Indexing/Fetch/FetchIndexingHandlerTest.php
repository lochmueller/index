<?php

declare(strict_types=1);

namespace Lochmueller\Index\Tests\Unit\Indexing\Fetch;

use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Lochmueller\Index\Configuration\Configuration;
use Lochmueller\Index\Configuration\ConfigurationLoader;
use Lochmueller\Index\ContentProcessing\ContentProcessor;
use Lochmueller\Index\Enums\IndexTechnology;
use Lochmueller\Index\Enums\IndexType;
use Lochmueller\Index\Event\IndexFileEvent;
use Lochmueller\Index\Event\IndexPageEvent;
use Lochmueller\Index\FileExtraction\FileExtractor;
use Lochmueller\Index\Indexing\Fetch\FetchIndexingHandler;
use Lochmueller\Index\Queue\Message\FetchIndexMessage;
use Lochmueller\Index\Tests\Unit\AbstractTest;
use Lochmueller\Index\Utility\FetchUtility;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use TYPO3\CMS\Core\Http\RequestFactory;
use TYPO3\CMS\Core\Site\Entity\Site;
use TYPO3\CMS\Core\Site\SiteFinder;

class FetchIndexingHandlerTest extends AbstractTest
{
    /** @var string[] */
    private array $requestedUrls = [];

    /** @var object[] */
    private array $dispatchedEvents = [];

    /**
     * @param array<string, array{string, string}> $pages URL => [content type, content]
     * @param string[] $fileExtensions
     */
    private function createSubject(array $pages, array $fileExtensions = [], bool $siteNotFound = false): FetchIndexingHandler
    {
        $this->requestedUrls = [];
        $this->dispatchedEvents = [];

        $siteFinder = $this->createStub(SiteFinder::class);
        if ($siteNotFound) {
            $siteFinder->method('getSiteByIdentifier')->willThrowException(new \Exception('Site not found'));
        } else {
            $siteFinder->method('getSiteByIdentifier')->willReturn($this->createStub(Site::class));
        }

        $client = $this->createStub(ClientInterface::class);
        $client->method('sendRequest')
            ->willReturnCallback(function (RequestInterface $request) use ($pages): ResponseInterface {
                $url = (string) $request->getUri();
                $this->requestedUrls[] = $url;
                if (!isset($pages[$url])) {
                    return new Response(404);
                }
                return new Response(200, ['Content-Type' => $pages[$url][0]], $pages[$url][1]);
            });

        $requestFactory = $this->createStub(RequestFactory::class);
        $requestFactory->method('createRequest')
            ->willReturnCallback(fn(string $method, string $uri): Request => new Request($method, $uri));

        $eventDispatcher = $this->createStub(EventDispatcherInterface::class);
        $eventDispatcher->method('dispatch')
            ->willReturnCallback(function (object $event): object {
                $this->dispatchedEvents[] = $event;
                return $event;
            });

        $fileExtractor = $this->createStub(FileExtractor::class);
        $fileExtractor->method('resolveFileTypes')->willReturn($fileExtensions);

        $configurationLoader = $this->createStub(ConfigurationLoader::class);
        $configurationLoader->method('loadByUid')->willReturn(new Configuration(
            configurationId: 42,
            pageId: 1,
            technology: IndexTechnology::Fetch,
            skipNoSearchPages: false,
            contentIndexing: false,
            levels: 0,
            fileMounts: [],
            fileTypes: ['pdf'],
            configuration: [],
            partialIndexing: [],
            languages: [],
        ));

        return new FetchIndexingHandler(
            $siteFinder,
            $eventDispatcher,
            new FetchUtility($client, $requestFactory),
            $fileExtractor,
            new ContentProcessor([]),
            $configurationLoader,
        );
    }

    private function createMessage(string $uri = 'https://example.com/docs/', int $depth = 3, bool $skipFiles = false): FetchIndexMessage
    {
        return new FetchIndexMessage(
            siteIdentifier: 'test-site',
            technology: IndexTechnology::Fetch,
            type: IndexType::Full,
            indexConfigurationRecordId: 42,
            indexProcessId: 'process-123',
            language: 0,
            uri: $uri,
            depth: $depth,
            skipFiles: $skipFiles,
        );
    }

    /**
     * @return string[]
     */
    private function getIndexedPageUris(): array
    {
        return array_values(array_map(
            static fn(IndexPageEvent $event): string => $event->uri,
            array_filter($this->dispatchedEvents, static fn(object $event): bool => $event instanceof IndexPageEvent),
        ));
    }

    public function testInvokeDispatchesIndexPageEventWithAbsoluteUrls(): void
    {
        $subject = $this->createSubject([
            'https://example.com/docs/' => ['text/html', '<html><title>Start</title><body><img src="logo.png"></body></html>'],
        ]);

        $subject->__invoke($this->createMessage());

        self::assertCount(1, $this->dispatchedEvents);
        $event = $this->dispatchedEvents[0];
        self::assertInstanceOf(IndexPageEvent::class, $event);
        self::assertSame('Start', $event->title);
        self::assertSame('https://example.com/docs/', $event->uri);
        self::assertSame(IndexTechnology::Fetch, $event->technology);
        self::assertSame(IndexType::Full, $event->type);
        self::assertSame(42, $event->indexConfigurationRecordId);
        self::assertSame('process-123', $event->indexProcessId);
        self::assertSame(-1, $event->pageUid);
        self::assertStringContainsString('src="https://example.com/docs/logo.png"', $event->content);
    }

    public function testInvokeFollowsLinksWithSameUrlBaseUpToDepth(): void
    {
        $subject = $this->createSubject([
            'https://example.com/docs/' => ['text/html', '<html><a href="a.html">A</a><a href="/other/">Other</a><a href="https://external.com/">Ext</a></html>'],
            'https://example.com/docs/a.html' => ['text/html', '<html><a href="b.html#x">B</a><a href="/docs/">Start</a></html>'],
            'https://example.com/docs/b.html' => ['text/html', '<html><a href="c.html">C</a></html>'],
            'https://example.com/docs/c.html' => ['text/html', '<html></html>'],
        ]);

        $subject->__invoke($this->createMessage(depth: 2));

        self::assertSame([
            'https://example.com/docs/',
            'https://example.com/docs/a.html',
            'https://example.com/docs/b.html',
        ], $this->getIndexedPageUris());
        self::assertNotContains('https://example.com/other/', $this->requestedUrls);
        self::assertNotContains('https://external.com/', $this->requestedUrls);
        self::assertNotContains('https://example.com/docs/c.html', $this->requestedUrls);
    }

    public function testInvokeWithDepthZeroIndexesOnlyStartUrl(): void
    {
        $subject = $this->createSubject([
            'https://example.com/docs/' => ['text/html', '<html><a href="a.html">A</a></html>'],
            'https://example.com/docs/a.html' => ['text/html', '<html></html>'],
        ]);

        $subject->__invoke($this->createMessage(depth: 0));

        self::assertSame(['https://example.com/docs/'], $this->getIndexedPageUris());
    }

    public function testInvokeSkipsNonHtmlAndFailedPages(): void
    {
        $subject = $this->createSubject([
            'https://example.com/docs/' => ['text/html', '<html><a href="image.png">I</a><a href="missing.html">M</a><a href="ok.html">O</a></html>'],
            'https://example.com/docs/image.png' => ['image/png', 'binary'],
            'https://example.com/docs/ok.html' => ['text/html', '<html></html>'],
        ]);

        $subject->__invoke($this->createMessage());

        self::assertSame(['https://example.com/docs/', 'https://example.com/docs/ok.html'], $this->getIndexedPageUris());
    }

    public function testInvokeDispatchesIndexFileEventForLinkedFiles(): void
    {
        $subject = $this->createSubject([
            'https://example.com/docs/' => ['text/html', '<html><a href="a.html">A</a></html>'],
            'https://example.com/docs/a.html' => ['text/html', '<html><a href="My%20File.pdf">PDF</a></html>'],
            'https://example.com/docs/My%20File.pdf' => ['application/pdf', '%PDF'],
        ], ['pdf']);

        $subject->__invoke($this->createMessage(depth: 1));

        $fileEvents = array_values(array_filter($this->dispatchedEvents, static fn(object $event): bool => $event instanceof IndexFileEvent));
        self::assertCount(1, $fileEvents);
        self::assertSame('My File', $fileEvents[0]->title);
        self::assertSame('https://example.com/docs/My%20File.pdf', $fileEvents[0]->uri);
        self::assertSame(42, $fileEvents[0]->indexConfigurationRecordId);
        self::assertSame('process-123', $fileEvents[0]->indexProcessId);
    }

    public function testInvokeSkipsFilesWhenRequested(): void
    {
        $subject = $this->createSubject([
            'https://example.com/docs/' => ['text/html', '<html><a href="file.pdf">PDF</a></html>'],
            'https://example.com/docs/file.pdf' => ['application/pdf', '%PDF'],
        ], ['pdf']);

        $subject->__invoke($this->createMessage(skipFiles: true));

        foreach ($this->dispatchedEvents as $event) {
            self::assertNotInstanceOf(IndexFileEvent::class, $event);
        }
    }

    public function testInvokeDispatchesNothingOnSiteFinderException(): void
    {
        $subject = $this->createSubject([
            'https://example.com/docs/' => ['text/html', '<html></html>'],
        ], siteNotFound: true);

        $subject->__invoke($this->createMessage());

        self::assertSame([], $this->dispatchedEvents);
        self::assertSame([], $this->requestedUrls);
    }
}
