<?php

declare(strict_types=1);

namespace Lochmueller\Index\Tests\Unit\Indexing;

use Lochmueller\Index\Configuration\Configuration;
use Lochmueller\Index\Enums\IndexTechnology;
use Lochmueller\Index\Indexing\ActiveIndexing;
use Lochmueller\Index\Indexing\Database\DatabaseIndexingQueue;
use Lochmueller\Index\Indexing\Frontend\FrontendIndexingQueue;
use Lochmueller\Index\Indexing\Http\HttpIndexingQueue;
use Lochmueller\Index\Tests\Unit\AbstractTest;

class ActiveIndexingTest extends AbstractTest
{
    public function testFillQueueCallsDatabaseIndexingQueueForDatabaseTechnology(): void
    {
        $configuration = $this->createConfiguration(IndexTechnology::Database);

        $databaseIndexQueue = $this->createMock(DatabaseIndexingQueue::class);
        $databaseIndexQueue->expects(self::once())
            ->method('fillQueue')
            ->with($configuration, false);

        $frontendIndexQueue = $this->createStub(FrontendIndexingQueue::class);
        $httpIndexingQueue = $this->createStub(HttpIndexingQueue::class);

        $subject = new ActiveIndexing($databaseIndexQueue, $frontendIndexQueue, $httpIndexingQueue);
        $subject->fillQueue($configuration);
    }

    public function testFillQueueCallsFrontendIndexingQueueForFrontendTechnology(): void
    {
        $configuration = $this->createConfiguration(IndexTechnology::Frontend);

        $databaseIndexQueue = $this->createStub(DatabaseIndexingQueue::class);

        $frontendIndexQueue = $this->createMock(FrontendIndexingQueue::class);
        $frontendIndexQueue->expects(self::once())
            ->method('fillQueue')
            ->with($configuration, false);

        $httpIndexingQueue = $this->createStub(HttpIndexingQueue::class);

        $subject = new ActiveIndexing($databaseIndexQueue, $frontendIndexQueue, $httpIndexingQueue);
        $subject->fillQueue($configuration);
    }

    public function testFillQueueCallsHttpIndexingQueueForHttpTechnology(): void
    {
        $configuration = $this->createConfiguration(IndexTechnology::Http);

        $databaseIndexQueue = $this->createStub(DatabaseIndexingQueue::class);
        $frontendIndexQueue = $this->createStub(FrontendIndexingQueue::class);

        $httpIndexingQueue = $this->createMock(HttpIndexingQueue::class);
        $httpIndexingQueue->expects(self::once())
            ->method('fillQueue')
            ->with($configuration, false);

        $subject = new ActiveIndexing($databaseIndexQueue, $frontendIndexQueue, $httpIndexingQueue);
        $subject->fillQueue($configuration);
    }

    public function testFillQueuePassesSkipFilesParameter(): void
    {
        $configuration = $this->createConfiguration(IndexTechnology::Database);

        $databaseIndexQueue = $this->createMock(DatabaseIndexingQueue::class);
        $databaseIndexQueue->expects(self::once())
            ->method('fillQueue')
            ->with($configuration, true);

        $frontendIndexQueue = $this->createStub(FrontendIndexingQueue::class);
        $httpIndexingQueue = $this->createStub(HttpIndexingQueue::class);

        $subject = new ActiveIndexing($databaseIndexQueue, $frontendIndexQueue, $httpIndexingQueue);
        $subject->fillQueue($configuration, true);
    }

    public function testFillQueueDoesNothingForNoneTechnology(): void
    {
        $configuration = $this->createConfiguration(IndexTechnology::None);

        $databaseIndexQueue = $this->createMock(DatabaseIndexingQueue::class);
        $databaseIndexQueue->expects(self::never())->method('fillQueue');

        $frontendIndexQueue = $this->createMock(FrontendIndexingQueue::class);
        $frontendIndexQueue->expects(self::never())->method('fillQueue');

        $httpIndexingQueue = $this->createMock(HttpIndexingQueue::class);
        $httpIndexingQueue->expects(self::never())->method('fillQueue');

        $subject = new ActiveIndexing($databaseIndexQueue, $frontendIndexQueue, $httpIndexingQueue);
        $subject->fillQueue($configuration);
    }

    public function testFillQueueDoesNothingForCacheTechnology(): void
    {
        $configuration = $this->createConfiguration(IndexTechnology::Cache);

        $databaseIndexQueue = $this->createMock(DatabaseIndexingQueue::class);
        $databaseIndexQueue->expects(self::never())->method('fillQueue');

        $frontendIndexQueue = $this->createMock(FrontendIndexingQueue::class);
        $frontendIndexQueue->expects(self::never())->method('fillQueue');

        $httpIndexingQueue = $this->createMock(HttpIndexingQueue::class);
        $httpIndexingQueue->expects(self::never())->method('fillQueue');

        $subject = new ActiveIndexing($databaseIndexQueue, $frontendIndexQueue, $httpIndexingQueue);
        $subject->fillQueue($configuration);
    }

    public function testFillQueueDoesNothingForExternalTechnology(): void
    {
        $configuration = $this->createConfiguration(IndexTechnology::External);

        $databaseIndexQueue = $this->createMock(DatabaseIndexingQueue::class);
        $databaseIndexQueue->expects(self::never())->method('fillQueue');

        $frontendIndexQueue = $this->createMock(FrontendIndexingQueue::class);
        $frontendIndexQueue->expects(self::never())->method('fillQueue');

        $httpIndexingQueue = $this->createMock(HttpIndexingQueue::class);
        $httpIndexingQueue->expects(self::never())->method('fillQueue');

        $subject = new ActiveIndexing($databaseIndexQueue, $frontendIndexQueue, $httpIndexingQueue);
        $subject->fillQueue($configuration);
    }

    private function createConfiguration(IndexTechnology $technology): Configuration
    {
        return new Configuration(
            configurationId: 1,
            pageId: 1,
            technology: $technology,
            skipNoSearchPages: false,
            contentIndexing: true,
            levels: 0,
            fileMounts: [],
            fileTypes: [],
            configuration: [],
            partialIndexing: [],
            languages: [],
        );
    }
}
