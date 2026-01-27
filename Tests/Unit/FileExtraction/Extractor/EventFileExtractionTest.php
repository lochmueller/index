<?php

declare(strict_types=1);

namespace Lochmueller\Index\Tests\Unit\FileExtraction\Extractor;

use Lochmueller\Index\Event\Extractor\CustomFileExtraction;
use Lochmueller\Index\FileExtraction\Extractor\EventFileExtraction;
use Lochmueller\Index\Tests\Unit\AbstractTest;
use Psr\EventDispatcher\EventDispatcherInterface;
use TYPO3\CMS\Core\Resource\FileInterface;

class EventFileExtractionTest extends AbstractTest
{
    public function testGetFileGroupNameReturnsEvent(): void
    {
        $eventDispatcher = $this->createStub(EventDispatcherInterface::class);
        $subject = new EventFileExtraction($eventDispatcher);

        self::assertSame('event', $subject->getFileGroupName());
    }

    public function testGetFileGroupLabelReturnsEventCustom(): void
    {
        $eventDispatcher = $this->createStub(EventDispatcherInterface::class);
        $subject = new EventFileExtraction($eventDispatcher);

        self::assertSame('Event / Custom', $subject->getFileGroupLabel());
    }

    public function testGetFileGroupIconIdentifierReturnsCorrectIcon(): void
    {
        $eventDispatcher = $this->createStub(EventDispatcherInterface::class);
        $subject = new EventFileExtraction($eventDispatcher);

        self::assertSame('avatar-default', $subject->getFileGroupIconIdentifier());
    }


    public function testGetFileExtensionsDispatchesEventAndReturnsExtensions(): void
    {
        $eventDispatcher = $this->createStub(EventDispatcherInterface::class);
        $eventDispatcher->method('dispatch')
            ->willReturnCallback(function (CustomFileExtraction $event) {
                $event->extensions = ['custom', 'ext'];
                return $event;
            });

        $subject = new EventFileExtraction($eventDispatcher);
        $result = $subject->getFileExtensions();

        self::assertSame(['custom', 'ext'], $result);
    }

    public function testGetFileContentDispatchesEventAndReturnsContent(): void
    {
        $file = $this->createStub(FileInterface::class);
        $eventDispatcher = $this->createStub(EventDispatcherInterface::class);
        $eventDispatcher->method('dispatch')
            ->willReturnCallback(function (CustomFileExtraction $event) {
                $event->content = 'Custom extracted content';
                return $event;
            });

        $subject = new EventFileExtraction($eventDispatcher);
        $result = $subject->getFileContent($file);

        self::assertSame('Custom extracted content', $result);
    }

    public function testGetFileContentReturnsEmptyStringWhenNoContentSet(): void
    {
        $file = $this->createStub(FileInterface::class);
        $eventDispatcher = $this->createStub(EventDispatcherInterface::class);
        $eventDispatcher->method('dispatch')
            ->willReturnCallback(fn(CustomFileExtraction $event) => $event);

        $subject = new EventFileExtraction($eventDispatcher);
        $result = $subject->getFileContent($file);

        self::assertSame('', $result);
    }
}
