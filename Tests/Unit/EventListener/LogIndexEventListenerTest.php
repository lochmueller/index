<?php

declare(strict_types=1);

namespace Lochmueller\Index\Tests\Unit\EventListener;

use Lochmueller\Index\Domain\Repository\LogRepository;
use Lochmueller\Index\Enums\IndexTechnology;
use Lochmueller\Index\Enums\IndexType;
use Lochmueller\Index\Event\FinishIndexProcessEvent;
use Lochmueller\Index\Event\IndexFileEvent;
use Lochmueller\Index\Event\IndexPageEvent;
use Lochmueller\Index\Event\StartIndexProcessEvent;
use Lochmueller\Index\EventListener\LogIndexEventListener;
use Lochmueller\Index\Tests\Unit\AbstractTest;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerInterface;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
use TYPO3\CMS\Core\Site\Entity\SiteInterface;

class LogIndexEventListenerTest extends AbstractTest
{
    public function testListenerImplementsLoggerAwareInterface(): void
    {
        $logRepositoryStub = $this->createStub(LogRepository::class);
        $extensionConfigurationStub = $this->createStub(ExtensionConfiguration::class);
        $subject = new LogIndexEventListener($logRepositoryStub, $extensionConfigurationStub);

        self::assertInstanceOf(LoggerAwareInterface::class, $subject);
    }

    public function testListenerCanSetLogger(): void
    {
        $loggerStub = $this->createStub(LoggerInterface::class);
        $logRepositoryStub = $this->createStub(LogRepository::class);
        $extensionConfigurationStub = $this->createStub(ExtensionConfiguration::class);

        $subject = new LogIndexEventListener($logRepositoryStub, $extensionConfigurationStub);
        $subject->setLogger($loggerStub);

        self::assertTrue(true);
    }

    public function testInvokeWithStartIndexProcessEventInsertsNewRecord(): void
    {
        $siteStub = $this->createStub(SiteInterface::class);
        $siteStub->method('getIdentifier')->willReturn('test-site');

        $event = new StartIndexProcessEvent(
            site: $siteStub,
            technology: IndexTechnology::Database,
            type: IndexType::Full,
            indexConfigurationRecordId: 1,
            indexProcessId: 'start-process-123',
            startTime: 1234567890.0,
        );

        /** @var LogRepository&MockObject $logRepositoryMock */
        $logRepositoryMock = $this->createMock(LogRepository::class);
        $logRepositoryMock->expects(self::once())
            ->method('findByIndexProcessId')
            ->with('start-process-123')
            ->willReturn(null);
        $logRepositoryMock->expects(self::once())
            ->method('insert')
            ->with([
                'index_process_id' => 'start-process-123',
                'start_time' => 1234567890,
            ]);
        $logRepositoryMock->expects(self::never())->method('update');

        $extensionConfigurationStub = $this->createStub(ExtensionConfiguration::class);
        $subject = new LogIndexEventListener($logRepositoryMock, $extensionConfigurationStub);
        $subject($event);
    }

    public function testInvokeWithStartIndexProcessEventUpdatesExistingRecord(): void
    {
        $siteStub = $this->createStub(SiteInterface::class);
        $siteStub->method('getIdentifier')->willReturn('test-site');

        $event = new StartIndexProcessEvent(
            site: $siteStub,
            technology: IndexTechnology::Database,
            type: IndexType::Full,
            indexConfigurationRecordId: 1,
            indexProcessId: 'start-process-456',
            startTime: 1234567890.0,
        );

        /** @var LogRepository&MockObject $logRepositoryMock */
        $logRepositoryMock = $this->createMock(LogRepository::class);
        $logRepositoryMock->expects(self::once())
            ->method('findByIndexProcessId')
            ->with('start-process-456')
            ->willReturn(['index_process_id' => 'start-process-456', 'pages_counter' => 5]);
        $logRepositoryMock->expects(self::never())->method('insert');
        $logRepositoryMock->expects(self::once())
            ->method('update')
            ->with(
                ['index_process_id' => 'start-process-456', 'pages_counter' => 5, 'start_time' => 1234567890],
                ['index_process_id' => 'start-process-456'],
            );

        $extensionConfigurationStub = $this->createStub(ExtensionConfiguration::class);
        $subject = new LogIndexEventListener($logRepositoryMock, $extensionConfigurationStub);
        $subject($event);
    }

    public function testInvokeWithIndexPageEventIncrementsPagesCounter(): void
    {
        $siteStub = $this->createStub(SiteInterface::class);
        $siteStub->method('getIdentifier')->willReturn('my-site');

        $event = new IndexPageEvent(
            site: $siteStub,
            technology: IndexTechnology::Frontend,
            type: IndexType::Partial,
            indexConfigurationRecordId: 42,
            indexProcessId: 'page-process-789',
            language: 0,
            title: 'Test Page',
            content: 'Test content',
            pageUid: 123,
            accessGroups: [0],
            uri: 'https://example.com/test-page',
        );

        /** @var LogRepository&MockObject $logRepositoryMock */
        $logRepositoryMock = $this->createMock(LogRepository::class);
        $logRepositoryMock->expects(self::once())
            ->method('findByIndexProcessId')
            ->with('page-process-789')
            ->willReturn(['index_process_id' => 'page-process-789', 'pages_counter' => 10]);
        $logRepositoryMock->expects(self::never())->method('insert');
        $logRepositoryMock->expects(self::once())
            ->method('update')
            ->with(
                ['index_process_id' => 'page-process-789', 'pages_counter' => 11],
                ['index_process_id' => 'page-process-789'],
            );

        $extensionConfigurationStub = $this->createStub(ExtensionConfiguration::class);
        $subject = new LogIndexEventListener($logRepositoryMock, $extensionConfigurationStub);
        $subject($event);
    }

    public function testInvokeWithIndexPageEventIncrementsFromZero(): void
    {
        $siteStub = $this->createStub(SiteInterface::class);
        $siteStub->method('getIdentifier')->willReturn('my-site');

        $event = new IndexPageEvent(
            site: $siteStub,
            technology: IndexTechnology::Frontend,
            type: IndexType::Partial,
            indexConfigurationRecordId: 42,
            indexProcessId: 'page-zero-process',
            language: 0,
            title: 'Test Page',
            content: 'Test content',
            pageUid: 123,
            accessGroups: [0],
            uri: 'https://example.com/test-page',
        );

        /** @var LogRepository&MockObject $logRepositoryMock */
        $logRepositoryMock = $this->createMock(LogRepository::class);
        $logRepositoryMock->expects(self::once())
            ->method('findByIndexProcessId')
            ->with('page-zero-process')
            ->willReturn(['index_process_id' => 'page-zero-process', 'pages_counter' => 0]);
        $logRepositoryMock->expects(self::never())->method('insert');
        $logRepositoryMock->expects(self::once())
            ->method('update')
            ->with(
                ['index_process_id' => 'page-zero-process', 'pages_counter' => 1],
                ['index_process_id' => 'page-zero-process'],
            );

        $extensionConfigurationStub = $this->createStub(ExtensionConfiguration::class);
        $subject = new LogIndexEventListener($logRepositoryMock, $extensionConfigurationStub);
        $subject($event);
    }

    public function testInvokeWithIndexFileEventIncrementsFilesCounter(): void
    {
        $siteStub = $this->createStub(SiteInterface::class);
        $siteStub->method('getIdentifier')->willReturn('file-site');

        $event = new IndexFileEvent(
            site: $siteStub,
            indexConfigurationRecordId: 10,
            indexProcessId: 'file-process-abc',
            title: 'Test Document',
            content: 'Document content',
            fileIdentifier: '1:/documents/test.pdf',
            uri: 'https://example.com/documents/test.pdf',
        );

        /** @var LogRepository&MockObject $logRepositoryMock */
        $logRepositoryMock = $this->createMock(LogRepository::class);
        $logRepositoryMock->expects(self::once())
            ->method('findByIndexProcessId')
            ->with('file-process-abc')
            ->willReturn(['index_process_id' => 'file-process-abc', 'files_counter' => 5]);
        $logRepositoryMock->expects(self::never())->method('insert');
        $logRepositoryMock->expects(self::once())
            ->method('update')
            ->with(
                ['index_process_id' => 'file-process-abc', 'files_counter' => 6],
                ['index_process_id' => 'file-process-abc'],
            );

        $extensionConfigurationStub = $this->createStub(ExtensionConfiguration::class);
        $subject = new LogIndexEventListener($logRepositoryMock, $extensionConfigurationStub);
        $subject($event);
    }

    public function testInvokeWithIndexFileEventIncrementsFromZero(): void
    {
        $siteStub = $this->createStub(SiteInterface::class);
        $siteStub->method('getIdentifier')->willReturn('file-site');

        $event = new IndexFileEvent(
            site: $siteStub,
            indexConfigurationRecordId: 10,
            indexProcessId: 'file-zero-process',
            title: 'Test Document',
            content: 'Document content',
            fileIdentifier: '1:/documents/test.pdf',
            uri: 'https://example.com/documents/test.pdf',
        );

        /** @var LogRepository&MockObject $logRepositoryMock */
        $logRepositoryMock = $this->createMock(LogRepository::class);
        $logRepositoryMock->expects(self::once())
            ->method('findByIndexProcessId')
            ->with('file-zero-process')
            ->willReturn(['index_process_id' => 'file-zero-process', 'files_counter' => 0]);
        $logRepositoryMock->expects(self::never())->method('insert');
        $logRepositoryMock->expects(self::once())
            ->method('update')
            ->with(
                ['index_process_id' => 'file-zero-process', 'files_counter' => 1],
                ['index_process_id' => 'file-zero-process'],
            );

        $extensionConfigurationStub = $this->createStub(ExtensionConfiguration::class);
        $subject = new LogIndexEventListener($logRepositoryMock, $extensionConfigurationStub);
        $subject($event);
    }

    public function testInvokeWithFinishIndexProcessEventSetsEndTimeAndDeletesOldEntries(): void
    {
        $siteStub = $this->createStub(SiteInterface::class);
        $siteStub->method('getIdentifier')->willReturn('finish-site');

        $event = new FinishIndexProcessEvent(
            site: $siteStub,
            technology: IndexTechnology::Database,
            type: IndexType::Full,
            indexConfigurationRecordId: 1,
            indexProcessId: 'finish-process-123',
            endTime: 1234567890.0,
        );

        /** @var LogRepository&MockObject $logRepositoryMock */
        $logRepositoryMock = $this->createMock(LogRepository::class);
        $logRepositoryMock->expects(self::once())
            ->method('findByIndexProcessId')
            ->with('finish-process-123')
            ->willReturn(['index_process_id' => 'finish-process-123', 'pages_counter' => 5]);
        $logRepositoryMock->expects(self::never())->method('insert');
        $logRepositoryMock->expects(self::once())
            ->method('update')
            ->with(
                ['index_process_id' => 'finish-process-123', 'pages_counter' => 5, 'end_time' => 1234567890],
                ['index_process_id' => 'finish-process-123'],
            );
        $logRepositoryMock->expects(self::once())
            ->method('deleteOlderThan');

        $extensionConfigurationStub = $this->createStub(ExtensionConfiguration::class);
        $extensionConfigurationStub->method('get')->willReturn(['keepIndexLogEntriesDays' => 14]);

        $subject = new LogIndexEventListener($logRepositoryMock, $extensionConfigurationStub);
        $subject($event);
    }

    public function testInvokeLogsDebugMessageWhenLoggerIsSet(): void
    {
        $siteStub = $this->createStub(SiteInterface::class);
        $siteStub->method('getIdentifier')->willReturn('debug-site');

        $event = new StartIndexProcessEvent(
            site: $siteStub,
            technology: IndexTechnology::Database,
            type: IndexType::Full,
            indexConfigurationRecordId: 1,
            indexProcessId: 'debug-process',
            startTime: 1234567890.0,
        );

        $logRepositoryStub = $this->createStub(LogRepository::class);
        $logRepositoryStub->method('findByIndexProcessId')->willReturn(null);

        /** @var LoggerInterface&MockObject $loggerMock */
        $loggerMock = $this->createMock(LoggerInterface::class);
        $loggerMock->expects(self::once())
            ->method('debug')
            ->with(
                'Execute ' . StartIndexProcessEvent::class,
                [
                    'site' => 'debug-site',
                    'technology' => 'database',
                    'uri' => null,
                ],
            );

        $extensionConfigurationStub = $this->createStub(ExtensionConfiguration::class);
        $subject = new LogIndexEventListener($logRepositoryStub, $extensionConfigurationStub);
        $subject->setLogger($loggerMock);
        $subject($event);
    }

    public function testInvokeLogsDebugMessageWithUriForIndexPageEvent(): void
    {
        $siteStub = $this->createStub(SiteInterface::class);
        $siteStub->method('getIdentifier')->willReturn('uri-site');

        $event = new IndexPageEvent(
            site: $siteStub,
            technology: IndexTechnology::Frontend,
            type: IndexType::Partial,
            indexConfigurationRecordId: 42,
            indexProcessId: 'uri-process',
            language: 0,
            title: 'Test Page',
            content: 'Test content',
            pageUid: 123,
            accessGroups: [0],
            uri: 'https://example.com/my-page',
        );

        $logRepositoryStub = $this->createStub(LogRepository::class);
        $logRepositoryStub->method('findByIndexProcessId')
            ->willReturn(['index_process_id' => 'uri-process', 'pages_counter' => 0]);

        /** @var LoggerInterface&MockObject $loggerMock */
        $loggerMock = $this->createMock(LoggerInterface::class);
        $loggerMock->expects(self::once())
            ->method('debug')
            ->with(
                'Execute ' . IndexPageEvent::class,
                [
                    'site' => 'uri-site',
                    'technology' => 'frontend',
                    'uri' => 'https://example.com/my-page',
                ],
            );

        $extensionConfigurationStub = $this->createStub(ExtensionConfiguration::class);
        $subject = new LogIndexEventListener($logRepositoryStub, $extensionConfigurationStub);
        $subject->setLogger($loggerMock);
        $subject($event);
    }

    public function testInvokeLogsDebugMessageForIndexFileEvent(): void
    {
        $siteStub = $this->createStub(SiteInterface::class);
        $siteStub->method('getIdentifier')->willReturn('file-log-site');

        $event = new IndexFileEvent(
            site: $siteStub,
            indexConfigurationRecordId: 10,
            indexProcessId: 'file-log-process',
            title: 'Test Document',
            content: 'Document content',
            fileIdentifier: '1:/documents/test.pdf',
            uri: 'https://example.com/documents/test.pdf',
        );

        $logRepositoryStub = $this->createStub(LogRepository::class);
        $logRepositoryStub->method('findByIndexProcessId')
            ->willReturn(['index_process_id' => 'file-log-process', 'files_counter' => 0]);

        /** @var LoggerInterface&MockObject $loggerMock */
        $loggerMock = $this->createMock(LoggerInterface::class);
        $loggerMock->expects(self::once())
            ->method('debug')
            ->with(
                'Execute ' . IndexFileEvent::class,
                [
                    'site' => 'file-log-site',
                    'technology' => null,
                    'uri' => 'https://example.com/documents/test.pdf',
                ],
            );

        $extensionConfigurationStub = $this->createStub(ExtensionConfiguration::class);
        $subject = new LogIndexEventListener($logRepositoryStub, $extensionConfigurationStub);
        $subject->setLogger($loggerMock);
        $subject($event);
    }

    public function testInvokeWorksWithoutLoggerSet(): void
    {
        $siteStub = $this->createStub(SiteInterface::class);
        $siteStub->method('getIdentifier')->willReturn('no-logger-site');

        $event = new StartIndexProcessEvent(
            site: $siteStub,
            technology: IndexTechnology::Database,
            type: IndexType::Full,
            indexConfigurationRecordId: 1,
            indexProcessId: 'no-logger-process',
            startTime: 1234567890.0,
        );

        $logRepositoryStub = $this->createStub(LogRepository::class);
        $logRepositoryStub->method('findByIndexProcessId')->willReturn(null);

        $extensionConfigurationStub = $this->createStub(ExtensionConfiguration::class);
        $subject = new LogIndexEventListener($logRepositoryStub, $extensionConfigurationStub);
        $subject($event);

        self::assertTrue(true);
    }
}
