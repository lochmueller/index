<?php

declare(strict_types=1);

namespace Lochmueller\Index\Tests\Unit\Queue;

use Lochmueller\Index\Queue\Bus;
use Lochmueller\Index\Tests\Unit\AbstractTest;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\TransportNamesStamp;

class BusTest extends AbstractTest
{
    protected function tearDown(): void
    {
        unset($GLOBALS['TYPO3_CONF_VARS']['SYS']['messenger']['routing']['Lochmueller\\Index\\Queue\\Message\\*']);
        parent::tearDown();
    }

    public function testDispatchWithoutTransportConfigurationDispatchesDirectly(): void
    {
        $message = new \stdClass();
        $messageBus = new class implements MessageBusInterface {
            public ?object $dispatchedMessage = null;

            public function dispatch(object $message, array $stamps = []): Envelope
            {
                $this->dispatchedMessage = $message;
                return new Envelope($message);
            }
        };

        $subject = new Bus($messageBus);
        $subject->dispatch($message);

        self::assertSame($message, $messageBus->dispatchedMessage);
    }

    public function testDispatchWithTransportConfigurationWrapsInEnvelopeWithStamp(): void
    {
        $GLOBALS['TYPO3_CONF_VARS']['SYS']['messenger']['routing']['Lochmueller\\Index\\Queue\\Message\\*'] = 'async';

        $message = new \stdClass();
        $messageBus = new class implements MessageBusInterface {
            public ?Envelope $dispatchedEnvelope = null;

            public function dispatch(object $message, array $stamps = []): Envelope
            {
                $this->dispatchedEnvelope = $message instanceof Envelope ? $message : new Envelope($message);
                return $this->dispatchedEnvelope;
            }
        };

        $subject = new Bus($messageBus);
        $subject->dispatch($message);

        self::assertInstanceOf(Envelope::class, $messageBus->dispatchedEnvelope);
        self::assertSame($message, $messageBus->dispatchedEnvelope->getMessage());

        $stamps = $messageBus->dispatchedEnvelope->all(TransportNamesStamp::class);
        self::assertCount(1, $stamps);
        self::assertSame(['async'], $stamps[0]->getTransportNames());
    }
}
