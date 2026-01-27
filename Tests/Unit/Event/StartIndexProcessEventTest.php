<?php

declare(strict_types=1);

namespace Lochmueller\Index\Tests\Unit\Event;

use Lochmueller\Index\Enums\IndexTechnology;
use Lochmueller\Index\Enums\IndexType;
use Lochmueller\Index\Event\StartIndexProcessEvent;
use Lochmueller\Index\Tests\Unit\AbstractTest;
use TYPO3\CMS\Core\Site\Entity\SiteInterface;

class StartIndexProcessEventTest extends AbstractTest
{
    public function testConstructorSetsAllProperties(): void
    {
        $site = $this->createStub(SiteInterface::class);
        $technology = IndexTechnology::Database;
        $type = IndexType::Full;
        $configId = 42;
        $processId = 'process-123';
        $startTime = 1234567890.123;

        $subject = new StartIndexProcessEvent(
            site: $site,
            technology: $technology,
            type: $type,
            indexConfigurationRecordId: $configId,
            indexProcessId: $processId,
            startTime: $startTime,
        );

        self::assertSame($site, $subject->site);
        self::assertSame($technology, $subject->technology);
        self::assertSame($type, $subject->type);
        self::assertSame($configId, $subject->indexConfigurationRecordId);
        self::assertSame($processId, $subject->indexProcessId);
        self::assertSame($startTime, $subject->startTime);
    }

    public function testConstructorWithNullConfigurationId(): void
    {
        $site = $this->createStub(SiteInterface::class);

        $subject = new StartIndexProcessEvent(
            site: $site,
            technology: IndexTechnology::Frontend,
            type: IndexType::Partial,
            indexConfigurationRecordId: null,
            indexProcessId: 'process-456',
            startTime: 9876543210.987,
        );

        self::assertNull($subject->indexConfigurationRecordId);
    }

    public function testEventIsReadonly(): void
    {
        $reflection = new \ReflectionClass(StartIndexProcessEvent::class);

        self::assertTrue($reflection->isReadOnly());
    }

    public function testAllTechnologiesCanBeUsed(): void
    {
        $site = $this->createStub(SiteInterface::class);

        foreach (IndexTechnology::cases() as $technology) {
            $subject = new StartIndexProcessEvent(
                site: $site,
                technology: $technology,
                type: IndexType::Full,
                indexConfigurationRecordId: 1,
                indexProcessId: 'process-' . $technology->value,
                startTime: microtime(true),
            );

            self::assertSame($technology, $subject->technology);
        }
    }

    public function testAllTypesCanBeUsed(): void
    {
        $site = $this->createStub(SiteInterface::class);

        foreach (IndexType::cases() as $type) {
            $subject = new StartIndexProcessEvent(
                site: $site,
                technology: IndexTechnology::Database,
                type: $type,
                indexConfigurationRecordId: 1,
                indexProcessId: 'process-' . $type->value,
                startTime: microtime(true),
            );

            self::assertSame($type, $subject->type);
        }
    }
}
