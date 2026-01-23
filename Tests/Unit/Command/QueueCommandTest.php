<?php

declare(strict_types=1);

namespace Lochmueller\Index\Tests\Unit\Command;

use Lochmueller\Index\Command\QueueCommand;
use Lochmueller\Index\Configuration\Configuration;
use Lochmueller\Index\Configuration\ConfigurationLoader;
use Lochmueller\Index\Enums\IndexTechnology;
use Lochmueller\Index\Indexing\ActiveIndexing;
use Lochmueller\Index\Tests\Unit\AbstractTest;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use TYPO3\CMS\Core\Site\Entity\Site;
use TYPO3\CMS\Core\Site\SiteFinder;

class QueueCommandTest extends AbstractTest
{
    private SiteFinder $siteFinder;
    private ConfigurationLoader $configurationLoader;
    private ActiveIndexing $activeIndexing;
    private QueueCommand $subject;

    protected function setUp(): void
    {
        parent::setUp();

        $this->siteFinder = $this->createMock(SiteFinder::class);
        $this->configurationLoader = $this->createMock(ConfigurationLoader::class);
        $this->activeIndexing = $this->createMock(ActiveIndexing::class);

        $this->subject = new QueueCommand(
            $this->siteFinder,
            $this->configurationLoader,
            $this->activeIndexing,
        );
    }

    public function testCommandHasCorrectName(): void
    {
        self::assertSame('index:queue', $this->subject->getName());
    }

    public function testCommandHasCorrectDescription(): void
    {
        self::assertStringContainsString('message queue', $this->subject->getDescription());
    }

    public function testCommandHasLimitSiteIdentifiersOption(): void
    {
        $definition = $this->subject->getDefinition();

        self::assertTrue($definition->hasOption('limitSiteIdentifiers'));
        self::assertSame('', $definition->getOption('limitSiteIdentifiers')->getDefault());
    }

    public function testCommandHasLimitConfigurationIdentifiersOption(): void
    {
        $definition = $this->subject->getDefinition();

        self::assertTrue($definition->hasOption('limitConfigurationIdentifiers'));
        self::assertSame('', $definition->getOption('limitConfigurationIdentifiers')->getDefault());
    }

    public function testExecuteReturnsSuccess(): void
    {
        $input = $this->createMock(InputInterface::class);
        $input->method('getOption')->willReturnMap([
            ['limitSiteIdentifiers', ''],
            ['limitConfigurationIdentifiers', ''],
        ]);

        $output = $this->createMock(OutputInterface::class);

        $this->siteFinder->method('getAllSites')->willReturn([]);

        $result = $this->invokeExecute($input, $output);

        self::assertSame(Command::SUCCESS, $result);
    }

    public function testExecuteWithLimitedConfigurationUids(): void
    {
        $configuration1 = $this->createConfiguration(1);
        $configuration2 = $this->createConfiguration(2);

        $input = $this->createMock(InputInterface::class);
        $input->method('getOption')->willReturnMap([
            ['limitSiteIdentifiers', ''],
            ['limitConfigurationIdentifiers', '1,2'],
        ]);

        $output = $this->createMock(OutputInterface::class);

        $this->configurationLoader->method('loadByUid')->willReturnMap([
            [1, $configuration1],
            [2, $configuration2],
        ]);

        $this->activeIndexing->expects(self::exactly(2))
            ->method('fillQueue')
            ->willReturnCallback(function (Configuration $config) use ($configuration1, $configuration2): void {
                self::assertContains($config, [$configuration1, $configuration2]);
            });

        $result = $this->invokeExecute($input, $output);

        self::assertSame(Command::SUCCESS, $result);
    }

    public function testExecuteWithAllSites(): void
    {
        $site1 = $this->createMock(Site::class);
        $site2 = $this->createMock(Site::class);

        $configuration1 = $this->createConfiguration(1);
        $configuration2 = $this->createConfiguration(2);

        $input = $this->createMock(InputInterface::class);
        $input->method('getOption')->willReturnMap([
            ['limitSiteIdentifiers', ''],
            ['limitConfigurationIdentifiers', ''],
        ]);

        $output = $this->createMock(OutputInterface::class);

        $this->siteFinder->method('getAllSites')->willReturn([$site1, $site2]);

        $this->configurationLoader->method('loadAllBySite')->willReturnCallback(
            function ($site) use ($site1, $site2, $configuration1, $configuration2): iterable {
                if ($site === $site1) {
                    yield $configuration1;
                }
                if ($site === $site2) {
                    yield $configuration2;
                }
            },
        );

        $this->activeIndexing->expects(self::exactly(2))->method('fillQueue');

        $result = $this->invokeExecute($input, $output);

        self::assertSame(Command::SUCCESS, $result);
    }

    public function testExecuteWithLimitedSiteIdentifiers(): void
    {
        $site = $this->createMock(Site::class);
        $configuration = $this->createConfiguration(1);

        $input = $this->createMock(InputInterface::class);
        $input->method('getOption')->willReturnMap([
            ['limitSiteIdentifiers', 'my-site'],
            ['limitConfigurationIdentifiers', ''],
        ]);

        $output = $this->createMock(OutputInterface::class);

        $this->siteFinder->method('getSiteByIdentifier')
            ->with('my-site')
            ->willReturn($site);

        $this->configurationLoader->method('loadAllBySite')
            ->with($site)
            ->willReturn([$configuration]);

        $this->activeIndexing->expects(self::once())
            ->method('fillQueue')
            ->with($configuration);

        $result = $this->invokeExecute($input, $output);

        self::assertSame(Command::SUCCESS, $result);
    }

    private function createConfiguration(int $uid): Configuration
    {
        return new Configuration(
            configurationId: $uid,
            pageId: 1,
            technology: IndexTechnology::Database,
            skipNoSearchPages: false,
            contentIndexing: true,
            levels: 99,
            fileMounts: [],
            fileTypes: [],
            configuration: [],
            partialIndexing: [],
            languages: [],
        );
    }

    private function invokeExecute(InputInterface $input, OutputInterface $output): int
    {
        $reflection = new \ReflectionMethod($this->subject, 'execute');
        return $reflection->invoke($this->subject, $input, $output);
    }
}
