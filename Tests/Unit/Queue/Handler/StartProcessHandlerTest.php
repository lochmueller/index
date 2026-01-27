<?php

declare(strict_types=1);

namespace Lochmueller\Index\Tests\Unit\Queue\Handler;

use Lochmueller\Index\Enums\IndexTechnology;
use Lochmueller\Index\Enums\IndexType;
use Lochmueller\Index\Event\StartIndexProcessEvent;
use Lochmueller\Index\Queue\Handler\StartProcessHandler;
use Lochmueller\Index\Queue\Message\StartProcessMessage;
use Lochmueller\Index\Tests\Unit\AbstractTest;
use Psr\EventDispatcher\EventDispatcherInterface;
use TYPO3\CMS\Core\Site\Entity\Site;
use TYPO3\CMS\Core\Site\SiteFinder;

class StartProcessHandlerTest extends AbstractTest
{
    public function testInvokeDispatchesStartIndexProcessEvent(): void
    {
        $siteIdentifier = 'test-site';
        $technology = IndexTechnology::Database;
        $type = IndexType::Full;
        $configurationRecordId = 42;
        $processId = 'process-123';

        $site = new Site($siteIdentifier, 1, []);

        $siteFinder = $this->createStub(SiteFinder::class);
        $siteFinder->method('getSiteByIdentifier')->with($siteIdentifier)->willReturn($site);

        $dispatchedEvent = null;
        $eventDispatcher = new class ($dispatchedEvent) implements EventDispatcherInterface {
            public function __construct(private ?object &$dispatchedEvent) {}

            public function dispatch(object $event): object
            {
                $this->dispatchedEvent = $event;
                return $event;
            }
        };

        $message = new StartProcessMessage(
            siteIdentifier: $siteIdentifier,
            technology: $technology,
            type: $type,
            indexConfigurationRecordId: $configurationRecordId,
            indexProcessId: $processId,
        );

        $subject = new StartProcessHandler($eventDispatcher, $siteFinder);
        $subject->__invoke($message);

        self::assertInstanceOf(StartIndexProcessEvent::class, $dispatchedEvent);
        self::assertSame($site, $dispatchedEvent->site);
        self::assertSame($technology, $dispatchedEvent->technology);
        self::assertSame($type, $dispatchedEvent->type);
        self::assertSame($configurationRecordId, $dispatchedEvent->indexConfigurationRecordId);
        self::assertSame($processId, $dispatchedEvent->indexProcessId);
        self::assertIsFloat($dispatchedEvent->startTime);
    }

    public function testInvokeWithNullConfigurationRecordId(): void
    {
        $siteIdentifier = 'test-site';
        $site = new Site($siteIdentifier, 1, []);

        $siteFinder = $this->createStub(SiteFinder::class);
        $siteFinder->method('getSiteByIdentifier')->willReturn($site);

        $dispatchedEvent = null;
        $eventDispatcher = new class ($dispatchedEvent) implements EventDispatcherInterface {
            public function __construct(private ?object &$dispatchedEvent) {}

            public function dispatch(object $event): object
            {
                $this->dispatchedEvent = $event;
                return $event;
            }
        };

        $message = new StartProcessMessage(
            siteIdentifier: $siteIdentifier,
            technology: IndexTechnology::External,
            type: IndexType::Partial,
            indexConfigurationRecordId: null,
            indexProcessId: 'process-456',
        );

        $subject = new StartProcessHandler($eventDispatcher, $siteFinder);
        $subject->__invoke($message);

        self::assertInstanceOf(StartIndexProcessEvent::class, $dispatchedEvent);
        self::assertNull($dispatchedEvent->indexConfigurationRecordId);
    }
}
