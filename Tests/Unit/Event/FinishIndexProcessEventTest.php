<?php

declare(strict_types=1);

namespace Lochmueller\Index\Tests\Unit\Event;

use Lochmueller\Index\Enums\IndexTechnology;
use Lochmueller\Index\Enums\IndexType;
use Lochmueller\Index\Event\FinishIndexProcessEvent;
use Lochmueller\Index\Tests\Unit\AbstractTest;
use TYPO3\CMS\Core\Site\Entity\SiteInterface;

class FinishIndexProcessEventTest extends AbstractTest
{
    public function testConstructorSetsAllProperties(): void
    {
        $site = $this->createStub(SiteInterface::class);
        $technology = IndexTechnology::Database;
        $type = IndexType::Full;
        $configId = 42;
        $processId = 'process-123';
        $endTime = 1234567890.123;

        $subject = new FinishIndexProcessEvent(
            site: $site,
            technology: $technology,
            type: $type,
            indexConfigurationRecordId: $configId,
            indexProcessId: $processId,
            endTime: $endTime,
        );

        self::assertSame($site, $subject->site);
        self::assertSame($technology, $subject->technology);
        self::assertSame($type, $subject->type);
        self::assertSame($configId, $subject->indexConfigurationRecordId);
        self::assertSame($processId, $subject->indexProcessId);
        self::assertSame($endTime, $subject->endTime);
    }

    public function testConstructorWithNullConfigurationId(): void
    {
        $site = $this->createStub(SiteInterface::class);

        $subject = new FinishIndexProcessEvent(
            site: $site,
            technology: IndexTechnology::Frontend,
            type: IndexType::Partial,
            indexConfigurationRecordId: null,
            indexProcessId: 'process-456',
            endTime: 9876543210.987,
        );

        self::assertNull($subject->indexConfigurationRecordId);
    }

    public function testEventIsReadonly(): void
    {
        $reflection = new \ReflectionClass(FinishIndexProcessEvent::class);

        self::assertTrue($reflection->isReadOnly());
    }
}
