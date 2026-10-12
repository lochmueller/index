<?php

declare(strict_types=1);

namespace Lochmueller\Index\Tests\Unit\Command;

use Lochmueller\Index\Command\PublishingDatesCommand;
use Lochmueller\Index\Configuration\Configuration;
use Lochmueller\Index\Configuration\ConfigurationLoader;
use Lochmueller\Index\Domain\Repository\GenericRepository;
use Lochmueller\Index\Enums\IndexTechnology;
use Lochmueller\Index\Enums\IndexType;
use Lochmueller\Index\Indexing\ActiveIndexing;
use Lochmueller\Index\Queue\Bus;
use Lochmueller\Index\Queue\DeIndexDocumentMessageFactory;
use Lochmueller\Index\Queue\Message\DeIndexDocumentMessage;
use Lochmueller\Index\Tests\Unit\AbstractTest;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use TYPO3\CMS\Core\Registry;

class PublishingDatesCommandTest extends AbstractTest
{
    public function testExpiredPagesAreDeIndexedAndPublishedRecordsAreIndexed(): void
    {
        $genericRepository = $this->createStub(GenericRepository::class);
        $table = '';
        $genericRepository->method('setTableName')->willReturnCallback(function (string $tableName) use (&$table, $genericRepository) {
            $table = $tableName;
            return $genericRepository;
        });
        $genericRepository->method('findByPublishingDateRange')->willReturnCallback(function () use (&$table): array {
            return match ($table) {
                'pages' => [
                    // Expired translation
                    ['uid' => 21, 'l10n_parent' => 20, 'sys_language_uid' => 1, 'endtime' => time() - 10],
                    // Published page
                    ['uid' => 30, 'l10n_parent' => 0, 'sys_language_uid' => 0, 'starttime' => time() - 10],
                ],
                'tt_content' => [
                    ['uid' => 100, 'pid' => 40],
                    ['uid' => 101, 'pid' => 30],
                ],
                default => [],
            };
        });

        $configurationLoader = $this->createStub(ConfigurationLoader::class);
        $configurationLoader->method('loadByPageTraversing')->willReturn($this->createConfiguration());

        $message = new DeIndexDocumentMessage(20, 1, 'https://example.com/en/page', 'main');
        $deIndexDocumentMessageFactory = $this->createMock(DeIndexDocumentMessageFactory::class);
        $deIndexDocumentMessageFactory->expects(self::once())
            ->method('createForPage')
            ->with(20, 1)
            ->willReturn([1 => $message]);

        $bus = $this->createMock(Bus::class);
        $bus->expects(self::once())->method('dispatch')->with($message);

        $indexedPages = [];
        $activeIndexing = $this->createMock(ActiveIndexing::class);
        $activeIndexing->expects(self::exactly(2))
            ->method('fillQueue')
            ->willReturnCallback(static function (Configuration $configuration, bool $skipFiles) use (&$indexedPages): void {
                self::assertSame(IndexType::Partial, $configuration->overrideIndexType);
                self::assertTrue($skipFiles);
                $indexedPages[] = $configuration->pageId;
            });

        $registry = $this->createMock(Registry::class);
        $registry->method('get')->willReturn(time() - 3600);
        $registry->expects(self::once())->method('set')->with('index', 'publishingDatesLastRun', self::isInt());

        $commandTester = new CommandTester(new PublishingDatesCommand(
            $genericRepository,
            $configurationLoader,
            $activeIndexing,
            $deIndexDocumentMessageFactory,
            $bus,
            $registry,
        ));

        self::assertSame(Command::SUCCESS, $commandTester->execute([]));
        self::assertSame([30, 40], $indexedPages);
        self::assertStringContainsString('1 documents de-indexed, 2 pages partially indexed.', $commandTester->getDisplay());
    }

    public function testRecordsOutsideOfIndexConfigurationsAreIgnored(): void
    {
        $genericRepository = $this->createStub(GenericRepository::class);
        $genericRepository->method('setTableName')->willReturnSelf();
        $genericRepository->method('findByPublishingDateRange')->willReturn([
            ['uid' => 30, 'pid' => 1, 'l10n_parent' => 0, 'sys_language_uid' => 0, 'endtime' => time() - 10],
        ]);

        $configurationLoader = $this->createStub(ConfigurationLoader::class);
        $configurationLoader->method('loadByPageTraversing')->willReturn(null);

        $bus = $this->createMock(Bus::class);
        $bus->expects(self::never())->method('dispatch');

        $activeIndexing = $this->createMock(ActiveIndexing::class);
        $activeIndexing->expects(self::never())->method('fillQueue');

        $commandTester = new CommandTester(new PublishingDatesCommand(
            $genericRepository,
            $configurationLoader,
            $activeIndexing,
            $this->createStub(DeIndexDocumentMessageFactory::class),
            $bus,
            $this->createStub(Registry::class),
        ));

        self::assertSame(Command::SUCCESS, $commandTester->execute([]));
    }

    private function createConfiguration(): Configuration
    {
        return new Configuration(
            configurationId: 1,
            pageId: 1,
            technology: IndexTechnology::Database,
            skipNoSearchPages: false,
            contentIndexing: false,
            levels: 99,
            fileMounts: [],
            fileTypes: [],
            configuration: [],
            partialIndexing: [],
            languages: [],
        );
    }
}
