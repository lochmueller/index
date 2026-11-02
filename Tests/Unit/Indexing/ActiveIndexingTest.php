<?php

declare(strict_types=1);

namespace Lochmueller\Index\Tests\Unit\Indexing;

use Lochmueller\Index\Configuration\Configuration;
use Lochmueller\Index\Enums\IndexTechnology;
use Lochmueller\Index\Indexing\ActiveIndexing;
use Lochmueller\Index\Indexing\IndexQueueInterface;
use Lochmueller\Index\Tests\Unit\AbstractTest;
use PHPUnit\Framework\Attributes\DataProvider;
use Psr\Log\LoggerInterface;

class ActiveIndexingTest extends AbstractTest
{
    public function testFillQueueCallsTheQueueOfTheTechnologyOfTheConfiguration(): void
    {
        $configuration = $this->createConfiguration(IndexTechnology::Frontend);

        $databaseQueue = $this->createQueue(IndexTechnology::Database);
        $frontendQueue = $this->createQueue(IndexTechnology::Frontend);
        $httpQueue = $this->createQueue(IndexTechnology::Http);

        $subject = new ActiveIndexing([$databaseQueue, $frontendQueue, $httpQueue]);
        $subject->fillQueue($configuration);

        self::assertSame([[$configuration, false]], $frontendQueue->calls);
        self::assertSame([], $databaseQueue->calls);
        self::assertSame([], $httpQueue->calls);
    }

    public function testFillQueuePassesSkipFilesParameter(): void
    {
        $configuration = $this->createConfiguration(IndexTechnology::Database);

        $databaseQueue = $this->createQueue(IndexTechnology::Database);

        $subject = new ActiveIndexing([$databaseQueue]);
        $subject->fillQueue($configuration, true);

        self::assertSame([[$configuration, true]], $databaseQueue->calls);
    }

    /**
     * Of two queues for the same technology the first one wins, so a shipped technology can be
     * replaced via the priority of the "index.technology" tag.
     */
    public function testFirstQueueOfATechnologyWins(): void
    {
        $configuration = $this->createConfiguration(IndexTechnology::Database);

        $ownQueue = $this->createQueue(IndexTechnology::Database);
        $shippedQueue = $this->createQueue(IndexTechnology::Database);

        $subject = new ActiveIndexing([$ownQueue, $shippedQueue]);
        $subject->fillQueue($configuration);

        self::assertCount(1, $ownQueue->calls);
        self::assertSame([], $shippedQueue->calls);
    }

    /**
     * @return array<string, array{IndexTechnology}>
     */
    public static function technologiesWithOwnEntryPointDataProvider(): array
    {
        return [
            'none' => [IndexTechnology::None],
            'cache' => [IndexTechnology::Cache],
            'external' => [IndexTechnology::External],
        ];
    }

    #[DataProvider('technologiesWithOwnEntryPointDataProvider')]
    public function testFillQueueDoesNothingForTechnologiesThatAreNotTriggeredByAConfiguration(IndexTechnology $technology): void
    {
        $queue = $this->createQueue($technology);

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::never())->method('warning');

        $subject = new ActiveIndexing([$queue]);
        $subject->setLogger($logger);
        $subject->fillQueue($this->createConfiguration($technology));

        self::assertSame([], $queue->calls);
    }

    public function testMissingQueueOfATechnologyIsLogged(): void
    {
        $configuration = $this->createConfiguration(IndexTechnology::Fetch);

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())
            ->method('warning')
            ->with('Index configuration is skipped because no queue handles its technology', [
                'technology' => 'fetch',
                'configurationId' => 1,
            ]);

        $subject = new ActiveIndexing([$this->createQueue(IndexTechnology::Database)]);
        $subject->setLogger($logger);
        $subject->fillQueue($configuration);
    }

    private function createQueue(IndexTechnology $technology): IndexQueueInterface
    {
        return new class ($technology) implements IndexQueueInterface {
            /** @var array<int, array{Configuration, bool}> */
            public array $calls = [];

            public function __construct(private readonly IndexTechnology $technology) {}

            public function getTechnology(): IndexTechnology
            {
                return $this->technology;
            }

            public function fillQueue(Configuration $configuration, bool $skipFiles = false): void
            {
                $this->calls[] = [$configuration, $skipFiles];
            }
        };
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
