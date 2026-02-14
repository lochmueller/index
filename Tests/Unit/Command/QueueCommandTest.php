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
    private QueueCommand $subject;

    protected function setUp(): void
    {
        parent::setUp();

        $this->subject = new QueueCommand(
            $this->createStub(SiteFinder::class),
            $this->createStub(ConfigurationLoader::class),
            $this->createStub(ActiveIndexing::class),
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
        $siteFinder = $this->createStub(SiteFinder::class);
        $siteFinder->method('getAllSites')->willReturn([]);

        $subject = new QueueCommand(
            $siteFinder,
            $this->createStub(ConfigurationLoader::class),
            $this->createStub(ActiveIndexing::class),
        );

        $input = $this->createStub(InputInterface::class);
        $input->method('getOption')->willReturnMap([
            ['limitSiteIdentifiers', ''],
            ['limitConfigurationIdentifiers', ''],
        ]);

        $output = $this->createStub(OutputInterface::class);

        $result = $this->invokeExecute($subject, $input, $output);

        self::assertSame(Command::SUCCESS, $result);
    }

    public function testExecuteWithLimitedConfigurationUids(): void
    {
        $configuration1 = $this->createConfiguration(1);
        $configuration2 = $this->createConfiguration(2);

        $configurationLoader = $this->createStub(ConfigurationLoader::class);
        $configurationLoader->method('loadByUid')->willReturnMap([
            [1, $configuration1],
            [2, $configuration2],
        ]);

        $activeIndexing = $this->createMock(ActiveIndexing::class);
        $activeIndexing->expects(self::exactly(2))
            ->method('fillQueue')
            ->willReturnCallback(function (Configuration $config) use ($configuration1, $configuration2): void {
                self::assertContains($config, [$configuration1, $configuration2]);
            });

        $subject = new QueueCommand(
            $this->createStub(SiteFinder::class),
            $configurationLoader,
            $activeIndexing,
        );

        $input = $this->createStub(InputInterface::class);
        $input->method('getOption')->willReturnMap([
            ['limitSiteIdentifiers', ''],
            ['limitConfigurationIdentifiers', '1,2'],
        ]);

        $output = $this->createStub(OutputInterface::class);

        $result = $this->invokeExecute($subject, $input, $output);

        self::assertSame(Command::SUCCESS, $result);
    }

    public function testExecuteWithAllSites(): void
    {
        $site1 = $this->createStub(Site::class);
        $site2 = $this->createStub(Site::class);

        $configuration1 = $this->createConfiguration(1);
        $configuration2 = $this->createConfiguration(2);

        $siteFinder = $this->createStub(SiteFinder::class);
        $siteFinder->method('getAllSites')->willReturn([$site1, $site2]);

        $configurationLoader = $this->createStub(ConfigurationLoader::class);
        $configurationLoader->method('loadAllBySite')->willReturnCallback(
            function ($site) use ($site1, $site2, $configuration1, $configuration2): iterable {
                if ($site === $site1) {
                    yield $configuration1;
                }
                if ($site === $site2) {
                    yield $configuration2;
                }
            },
        );

        $activeIndexing = $this->createMock(ActiveIndexing::class);
        $activeIndexing->expects(self::exactly(2))->method('fillQueue');

        $subject = new QueueCommand(
            $siteFinder,
            $configurationLoader,
            $activeIndexing,
        );

        $input = $this->createStub(InputInterface::class);
        $input->method('getOption')->willReturnMap([
            ['limitSiteIdentifiers', ''],
            ['limitConfigurationIdentifiers', ''],
        ]);

        $output = $this->createStub(OutputInterface::class);

        $result = $this->invokeExecute($subject, $input, $output);

        self::assertSame(Command::SUCCESS, $result);
    }

    public function testExecuteWithLimitedSiteIdentifiers(): void
    {
        $site = $this->createStub(Site::class);
        $configuration = $this->createConfiguration(1);

        $siteFinder = $this->createStub(SiteFinder::class);
        $siteFinder->method('getSiteByIdentifier')
            ->willReturn($site);

        $configurationLoader = $this->createStub(ConfigurationLoader::class);
        $configurationLoader->method('loadAllBySite')
            ->willReturn([$configuration]);

        $activeIndexing = $this->createMock(ActiveIndexing::class);
        $activeIndexing->expects(self::once())
            ->method('fillQueue')
            ->with($configuration);

        $subject = new QueueCommand(
            $siteFinder,
            $configurationLoader,
            $activeIndexing,
        );

        $input = $this->createStub(InputInterface::class);
        $input->method('getOption')->willReturnMap([
            ['limitSiteIdentifiers', 'my-site'],
            ['limitConfigurationIdentifiers', ''],
        ]);

        $output = $this->createStub(OutputInterface::class);

        $result = $this->invokeExecute($subject, $input, $output);

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

    private function invokeExecute(QueueCommand $subject, InputInterface $input, OutputInterface $output): int
    {
        $reflection = new \ReflectionMethod($subject, 'execute');
        return $reflection->invoke($subject, $input, $output);
    }
}
