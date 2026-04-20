<?php

declare(strict_types=1);

namespace Lochmueller\Index\Tests\Unit\ContentProcessing;

use Lochmueller\Index\ContentProcessing\EventContentProcessor;
use Lochmueller\Index\Event\ModifyContentEvent;
use Lochmueller\Index\Tests\Unit\AbstractTest;
use Psr\EventDispatcher\EventDispatcherInterface;

class EventContentProcessorTest extends AbstractTest
{
    public function testProcessReturnsContentUnchangedWhenNoListenerModifiesIt(): void
    {
        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $eventDispatcher
            ->expects(self::once())
            ->method('dispatch')
            ->with(self::isInstanceOf(ModifyContentEvent::class))
            ->willReturnArgument(0);

        $subject = new EventContentProcessor($eventDispatcher);

        self::assertSame('<p>Hello</p>', $subject->process('<p>Hello</p>'));
    }

    public function testProcessReturnsContentModifiedByListener(): void
    {
        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $eventDispatcher
            ->expects(self::once())
            ->method('dispatch')
            ->willReturnCallback(static function (ModifyContentEvent $event): ModifyContentEvent {
                $event->content = strtoupper($event->content);
                return $event;
            });

        $subject = new EventContentProcessor($eventDispatcher);

        self::assertSame('HELLO', $subject->process('hello'));
    }

    public function testProcessPassesOriginalContentIntoEvent(): void
    {
        $receivedContent = null;
        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $eventDispatcher
            ->expects(self::once())
            ->method('dispatch')
            ->willReturnCallback(static function (ModifyContentEvent $event) use (&$receivedContent): ModifyContentEvent {
                $receivedContent = $event->content;
                return $event;
            });

        $subject = new EventContentProcessor($eventDispatcher);
        $subject->process('original');

        self::assertSame('original', $receivedContent);
    }

    public function testGetLabelReturnsLocallangReference(): void
    {
        $subject = new EventContentProcessor($this->createStub(EventDispatcherInterface::class));

        self::assertStringContainsString(
            'tx_index_domain_model_configuration.content_processors.type.event',
            $subject->getLabel()
        );
    }
}
