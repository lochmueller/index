<?php

declare(strict_types=1);

namespace Lochmueller\Index\Tests\Unit\Indexing\External;

use Lochmueller\Index\Indexing\External\ExternalIndexingQueue;
use Lochmueller\Index\Queue\Bus;
use Lochmueller\Index\Queue\Message\ExternalFileIndexMessage;
use Lochmueller\Index\Queue\Message\ExternalPageIndexMessage;
use Lochmueller\Index\Queue\Message\FinishProcessMessage;
use Lochmueller\Index\Queue\Message\StartProcessMessage;
use Lochmueller\Index\Tests\Unit\AbstractTest;
use TYPO3\CMS\Core\Site\Entity\Site;
use TYPO3\CMS\Core\Site\SiteFinder;

class ExternalIndexingQueueTest extends AbstractTest
{
    public function testFillQueueDispatchesStartAndFinishMessages(): void
    {
        $site = $this->createStub(Site::class);
        $site->method('getIdentifier')->willReturn('test-site');

        $dispatchedMessages = [];
        $bus = $this->createMock(Bus::class);
        $bus->expects(self::exactly(3))
            ->method('dispatch')
            ->willReturnCallback(function ($message) use (&$dispatchedMessages): void {
                $dispatchedMessages[] = $message;
            });

        $siteFinder = $this->createStub(SiteFinder::class);

        $subject = new ExternalIndexingQueue($bus, $siteFinder);
        $subject->fillQueue($site, 0, ['uri' => 'https://example.com', 'title' => 'Test', 'content' => 'Content']);

        self::assertInstanceOf(StartProcessMessage::class, $dispatchedMessages[0]);
        self::assertInstanceOf(ExternalFileIndexMessage::class, $dispatchedMessages[1]);
        self::assertInstanceOf(FinishProcessMessage::class, $dispatchedMessages[2]);
    }

    public function testFillQueueDispatchesPageMessageWhenIsPageTrue(): void
    {
        $site = $this->createStub(Site::class);
        $site->method('getIdentifier')->willReturn('test-site');

        $dispatchedMessages = [];
        $bus = $this->createMock(Bus::class);
        $bus->expects(self::exactly(3))
            ->method('dispatch')
            ->willReturnCallback(function ($message) use (&$dispatchedMessages): void {
                $dispatchedMessages[] = $message;
            });

        $siteFinder = $this->createStub(SiteFinder::class);

        $subject = new ExternalIndexingQueue($bus, $siteFinder);
        $subject->fillQueue($site, 0, ['uri' => 'https://example.com', 'title' => 'Test', 'content' => 'Content'], true);

        self::assertInstanceOf(StartProcessMessage::class, $dispatchedMessages[0]);
        self::assertInstanceOf(ExternalPageIndexMessage::class, $dispatchedMessages[1]);
        self::assertInstanceOf(FinishProcessMessage::class, $dispatchedMessages[2]);
    }

    public function testFillQueueUsesProvidedInfoValues(): void
    {
        $site = $this->createStub(Site::class);
        $site->method('getIdentifier')->willReturn('test-site');

        $capturedMessage = null;
        $messageBus = $this->createStub(\Symfony\Component\Messenger\MessageBusInterface::class);
        $messageBus->method('dispatch')->willReturnCallback(function ($envelope) use (&$capturedMessage) {
            $message = $envelope instanceof \Symfony\Component\Messenger\Envelope ? $envelope->getMessage() : $envelope;
            if ($message instanceof ExternalFileIndexMessage) {
                $capturedMessage = $message;
            }
            return $envelope instanceof \Symfony\Component\Messenger\Envelope ? $envelope : new \Symfony\Component\Messenger\Envelope($envelope);
        });
        $bus = new Bus($messageBus);

        $siteFinder = $this->createStub(SiteFinder::class);

        $subject = new ExternalIndexingQueue($bus, $siteFinder);
        $subject->fillQueue($site, 1, [
            'uri' => 'https://example.com/file.pdf',
            'title' => 'My File',
            'content' => 'File content here',
        ]);

        self::assertNotNull($capturedMessage);
        self::assertSame('https://example.com/file.pdf', $capturedMessage->uri);
        self::assertSame('My File', $capturedMessage->title);
        self::assertSame('File content here', $capturedMessage->content);
        self::assertSame(1, $capturedMessage->language);
    }

    public function testFillQueueHandlesMissingInfoValues(): void
    {
        $site = $this->createStub(Site::class);
        $site->method('getIdentifier')->willReturn('test-site');

        $capturedMessage = null;
        $messageBus = $this->createStub(\Symfony\Component\Messenger\MessageBusInterface::class);
        $messageBus->method('dispatch')->willReturnCallback(function ($envelope) use (&$capturedMessage) {
            $message = $envelope instanceof \Symfony\Component\Messenger\Envelope ? $envelope->getMessage() : $envelope;
            if ($message instanceof ExternalFileIndexMessage) {
                $capturedMessage = $message;
            }
            return $envelope instanceof \Symfony\Component\Messenger\Envelope ? $envelope : new \Symfony\Component\Messenger\Envelope($envelope);
        });
        $bus = new Bus($messageBus);

        $siteFinder = $this->createStub(SiteFinder::class);

        $subject = new ExternalIndexingQueue($bus, $siteFinder);
        $subject->fillQueue($site, 0, []);

        self::assertNotNull($capturedMessage);
        self::assertSame('', $capturedMessage->uri);
        self::assertSame('', $capturedMessage->title);
        self::assertSame('', $capturedMessage->content);
    }
}
