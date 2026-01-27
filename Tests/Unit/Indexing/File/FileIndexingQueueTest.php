<?php

declare(strict_types=1);

namespace Lochmueller\Index\Tests\Unit\Indexing\File;

use Lochmueller\Index\Configuration\Configuration;
use Lochmueller\Index\Enums\IndexTechnology;
use Lochmueller\Index\FileExtraction\FileExtractor;
use Lochmueller\Index\Indexing\File\FileIndexingQueue;
use Lochmueller\Index\Queue\Bus;
use Lochmueller\Index\Queue\Message\FileMessage;
use Lochmueller\Index\Tests\Unit\AbstractTest;
use Lochmueller\Index\Traversing\FileTraversing;
use TYPO3\CMS\Core\Resource\File;
use TYPO3\CMS\Core\Site\Entity\Site;

class FileIndexingQueueTest extends AbstractTest
{
    private function createConfiguration(array $fileMounts = [], array $fileTypes = []): Configuration
    {
        return new Configuration(
            configurationId: 1,
            pageId: 1,
            technology: IndexTechnology::Database,
            skipNoSearchPages: false,
            contentIndexing: true,
            levels: 0,
            fileMounts: $fileMounts,
            fileTypes: $fileTypes,
            configuration: [],
            partialIndexing: [],
            languages: [],
        );
    }

    public function testFillQueueDispatchesFileMessages(): void
    {
        $file = $this->createStub(File::class);
        $file->method('getCombinedIdentifier')->willReturn('1:/test.pdf');

        $fileTraversing = $this->createStub(FileTraversing::class);
        $fileTraversing->method('findFilesInFileMountUidRecursive')->willReturn([$file]);

        $fileExtractor = $this->createStub(FileExtractor::class);
        $fileExtractor->method('resolveFileTypes')->willReturn(['pdf']);

        $site = $this->createStub(Site::class);
        $site->method('getIdentifier')->willReturn('test-site');

        $bus = $this->createMock(Bus::class);
        $bus->expects(self::once())
            ->method('dispatch')
            ->with(self::callback(fn(FileMessage $message): bool => $message->siteIdentifier === 'test-site'
                    && $message->indexConfigurationRecordId === 1
                    && $message->fileIdentifier === '1:/test.pdf'
                    && $message->indexProcessId === 'process-123'));

        $subject = new FileIndexingQueue($fileTraversing, $fileExtractor, $bus);
        $subject->fillQueue($this->createConfiguration(['1'], ['pdf']), $site, 'process-123');
    }

    public function testFillQueueHandlesMultipleFileMounts(): void
    {
        $file1 = $this->createStub(File::class);
        $file1->method('getCombinedIdentifier')->willReturn('1:/file1.pdf');

        $file2 = $this->createStub(File::class);
        $file2->method('getCombinedIdentifier')->willReturn('2:/file2.pdf');

        $fileTraversing = $this->createStub(FileTraversing::class);
        $fileTraversing->method('findFilesInFileMountUidRecursive')
            ->willReturnOnConsecutiveCalls([$file1], [$file2]);

        $fileExtractor = $this->createStub(FileExtractor::class);
        $fileExtractor->method('resolveFileTypes')->willReturn(['pdf']);

        $site = $this->createStub(Site::class);
        $site->method('getIdentifier')->willReturn('test-site');

        $bus = $this->createMock(Bus::class);
        $bus->expects(self::exactly(2))->method('dispatch');

        $subject = new FileIndexingQueue($fileTraversing, $fileExtractor, $bus);
        $subject->fillQueue($this->createConfiguration(['1', '2'], ['pdf']), $site, 'process-123');
    }

    public function testFillQueueHandlesExceptionGracefully(): void
    {
        $fileTraversing = $this->createStub(FileTraversing::class);

        $fileExtractor = $this->createStub(FileExtractor::class);
        $fileExtractor->method('resolveFileTypes')->willThrowException(new \Exception('Error'));

        $site = $this->createStub(Site::class);

        $bus = $this->createMock(Bus::class);
        $bus->expects(self::never())->method('dispatch');

        $subject = new FileIndexingQueue($fileTraversing, $fileExtractor, $bus);
        $subject->fillQueue($this->createConfiguration(['1'], ['pdf']), $site, 'process-123');
    }

    public function testFillQueueHandlesEmptyFileMounts(): void
    {
        $fileTraversing = $this->createStub(FileTraversing::class);
        $fileExtractor = $this->createStub(FileExtractor::class);
        $fileExtractor->method('resolveFileTypes')->willReturn([]);

        $site = $this->createStub(Site::class);

        $bus = $this->createMock(Bus::class);
        $bus->expects(self::never())->method('dispatch');

        $subject = new FileIndexingQueue($fileTraversing, $fileExtractor, $bus);
        $subject->fillQueue($this->createConfiguration([], []), $site, 'process-123');
    }
}
