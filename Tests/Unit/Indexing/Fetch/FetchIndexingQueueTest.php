<?php

declare(strict_types=1);

namespace Lochmueller\Index\Tests\Unit\Indexing\Fetch;

use Lochmueller\Index\Configuration\Configuration;
use Lochmueller\Index\Enums\IndexTechnology;
use Lochmueller\Index\Enums\IndexType;
use Lochmueller\Index\Indexing\Fetch\FetchIndexingQueue;
use Lochmueller\Index\Queue\Bus;
use Lochmueller\Index\Queue\Message\FetchIndexMessage;
use Lochmueller\Index\Queue\Message\FinishProcessMessage;
use Lochmueller\Index\Queue\Message\StartProcessMessage;
use Lochmueller\Index\Tests\Unit\AbstractTest;
use TYPO3\CMS\Core\Site\Entity\Site;
use TYPO3\CMS\Core\Site\Entity\SiteLanguage;
use TYPO3\CMS\Core\Site\SiteFinder;

class FetchIndexingQueueTest extends AbstractTest
{
    private function createConfiguration(string $fetchUrl = 'https://example.com/', int $fetchDepth = 3, ?IndexType $overrideIndexType = null): Configuration
    {
        return new Configuration(
            configurationId: 42,
            pageId: 1,
            technology: IndexTechnology::Fetch,
            skipNoSearchPages: false,
            contentIndexing: false,
            levels: 0,
            fileMounts: [],
            fileTypes: [],
            configuration: [],
            partialIndexing: [],
            languages: [],
            overrideIndexType: $overrideIndexType,
            fetchUrl: $fetchUrl,
            fetchDepth: $fetchDepth,
        );
    }

    private function createSiteFinder(): SiteFinder
    {
        $language = $this->createStub(SiteLanguage::class);
        $language->method('getLanguageId')->willReturn(0);

        $site = $this->createStub(Site::class);
        $site->method('getIdentifier')->willReturn('test-site');
        $site->method('getDefaultLanguage')->willReturn($language);

        $siteFinder = $this->createStub(SiteFinder::class);
        $siteFinder->method('getSiteByPageId')->willReturn($site);

        return $siteFinder;
    }

    public function testFillQueueDispatchesStartFetchAndFinishMessages(): void
    {
        $dispatchedMessages = [];
        $bus = $this->createStub(Bus::class);
        $bus->method('dispatch')
            ->willReturnCallback(function (object $message) use (&$dispatchedMessages): void {
                $dispatchedMessages[] = $message;
            });

        $subject = new FetchIndexingQueue($bus, $this->createSiteFinder());
        $subject->fillQueue($this->createConfiguration('https://example.com/docs/', 2), true);

        self::assertCount(3, $dispatchedMessages);
        self::assertInstanceOf(StartProcessMessage::class, $dispatchedMessages[0]);
        self::assertInstanceOf(FetchIndexMessage::class, $dispatchedMessages[1]);
        self::assertInstanceOf(FinishProcessMessage::class, $dispatchedMessages[2]);

        self::assertSame(IndexTechnology::Fetch, $dispatchedMessages[0]->technology);
        self::assertSame(IndexType::Full, $dispatchedMessages[0]->type);
        self::assertSame(42, $dispatchedMessages[0]->indexConfigurationRecordId);

        self::assertSame('test-site', $dispatchedMessages[1]->siteIdentifier);
        self::assertSame('https://example.com/docs/', $dispatchedMessages[1]->uri);
        self::assertSame(2, $dispatchedMessages[1]->depth);
        self::assertTrue($dispatchedMessages[1]->skipFiles);

        self::assertStringStartsWith('fetch-index', $dispatchedMessages[0]->indexProcessId);
        self::assertSame($dispatchedMessages[0]->indexProcessId, $dispatchedMessages[1]->indexProcessId);
        self::assertSame($dispatchedMessages[1]->indexProcessId, $dispatchedMessages[2]->indexProcessId);
    }

    public function testFillQueueUsesOverrideIndexType(): void
    {
        $dispatchedMessages = [];
        $bus = $this->createStub(Bus::class);
        $bus->method('dispatch')
            ->willReturnCallback(function (object $message) use (&$dispatchedMessages): void {
                $dispatchedMessages[] = $message;
            });

        $subject = new FetchIndexingQueue($bus, $this->createSiteFinder());
        $subject->fillQueue($this->createConfiguration(overrideIndexType: IndexType::Partial));

        self::assertSame(IndexType::Partial, $dispatchedMessages[1]->type);
    }

    public function testFillQueueNormalizesNegativeDepth(): void
    {
        $dispatchedMessages = [];
        $bus = $this->createStub(Bus::class);
        $bus->method('dispatch')
            ->willReturnCallback(function (object $message) use (&$dispatchedMessages): void {
                $dispatchedMessages[] = $message;
            });

        $subject = new FetchIndexingQueue($bus, $this->createSiteFinder());
        $subject->fillQueue($this->createConfiguration(fetchDepth: -5));

        self::assertSame(0, $dispatchedMessages[1]->depth);
    }

    public function testFillQueueDoesNothingWithoutUrl(): void
    {
        $bus = $this->createMock(Bus::class);
        $bus->expects(self::never())->method('dispatch');

        $subject = new FetchIndexingQueue($bus, $this->createSiteFinder());
        $subject->fillQueue($this->createConfiguration(''));
    }
}
