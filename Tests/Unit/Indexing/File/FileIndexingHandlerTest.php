<?php

declare(strict_types=1);

namespace Lochmueller\Index\Tests\Unit\Indexing\File;

use Lochmueller\Index\Event\IndexFileEvent;
use Lochmueller\Index\FileExtraction\FileExtractor;
use Lochmueller\Index\Indexing\File\FileIndexingHandler;
use Lochmueller\Index\Queue\Message\FileMessage;
use Lochmueller\Index\Tests\Unit\AbstractTest;
use Lochmueller\Index\Traversing\FileTraversing;
use Psr\EventDispatcher\EventDispatcherInterface;
use TYPO3\CMS\Core\Resource\File;
use TYPO3\CMS\Core\Site\Entity\Site;
use TYPO3\CMS\Core\Site\SiteFinder;

class FileIndexingHandlerTest extends AbstractTest
{
    public function testInvokeReturnsEarlyWhenFileNotFound(): void
    {
        $fileTraversing = $this->createStub(FileTraversing::class);
        $fileTraversing->method('getFileByCompinedIdentifier')->willReturn(null);

        $fileExtractor = $this->createStub(FileExtractor::class);
        $siteFinder = $this->createStub(SiteFinder::class);

        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $eventDispatcher->expects(self::never())->method('dispatch');

        $subject = new FileIndexingHandler($fileTraversing, $fileExtractor, $eventDispatcher, $siteFinder);

        $message = new FileMessage(
            siteIdentifier: 'test-site',
            indexConfigurationRecordId: 1,
            fileIdentifier: '1:/test.pdf',
            indexProcessId: 'process-123',
        );

        $subject->__invoke($message);
    }

    public function testInvokeDispatchesIndexFileEvent(): void
    {
        $file = $this->createStub(File::class);
        $file->method('getProperty')->willReturnMap([
            ['title', 'File Title'],
            ['alternative', 'Alt Text'],
            ['description', 'Description'],
            ['name', 'test.pdf'],
        ]);
        $file->method('getNameWithoutExtension')->willReturn('test');

        $fileTraversing = $this->createStub(FileTraversing::class);
        $fileTraversing->method('getFileByCompinedIdentifier')->willReturn($file);

        $fileExtractor = $this->createStub(FileExtractor::class);
        $fileExtractor->method('extract')->willReturn(' Extracted content');

        $site = $this->createStub(Site::class);
        $siteFinder = $this->createStub(SiteFinder::class);
        $siteFinder->method('getSiteByIdentifier')->willReturn($site);

        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $eventDispatcher->expects(self::once())
            ->method('dispatch')
            ->with(self::callback(fn(IndexFileEvent $event): bool => $event->site === $site
                    && $event->indexConfigurationRecordId === 1
                    && $event->indexProcessId === 'process-123'
                    && $event->title === 'test'
                    && str_contains($event->content, 'File Title')
                    && str_contains($event->content, 'Extracted content')
                    && $event->fileIdentifier === '1:/test.pdf'));

        $subject = new FileIndexingHandler($fileTraversing, $fileExtractor, $eventDispatcher, $siteFinder);

        $message = new FileMessage(
            siteIdentifier: 'test-site',
            indexConfigurationRecordId: 1,
            fileIdentifier: '1:/test.pdf',
            indexProcessId: 'process-123',
        );

        $subject->__invoke($message);
    }

    public function testInvokeReturnsEarlyOnExtractionException(): void
    {
        $file = $this->createStub(File::class);
        $file->method('getProperty')->willReturn('');

        $fileTraversing = $this->createStub(FileTraversing::class);
        $fileTraversing->method('getFileByCompinedIdentifier')->willReturn($file);

        $fileExtractor = $this->createStub(FileExtractor::class);
        $fileExtractor->method('extract')->willThrowException(new \Exception('Extraction failed'));

        $siteFinder = $this->createStub(SiteFinder::class);

        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $eventDispatcher->expects(self::never())->method('dispatch');

        $subject = new FileIndexingHandler($fileTraversing, $fileExtractor, $eventDispatcher, $siteFinder);

        $message = new FileMessage(
            siteIdentifier: 'test-site',
            indexConfigurationRecordId: 1,
            fileIdentifier: '1:/test.pdf',
            indexProcessId: 'process-123',
        );

        $subject->__invoke($message);
    }
}
