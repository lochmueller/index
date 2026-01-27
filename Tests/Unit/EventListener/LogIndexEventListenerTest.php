<?php

declare(strict_types=1);

namespace Lochmueller\Index\Tests\Unit\EventListener;

use Lochmueller\Index\Enums\IndexTechnology;
use Lochmueller\Index\Enums\IndexType;
use Lochmueller\Index\Event\FinishIndexProcessEvent;
use Lochmueller\Index\Event\IndexFileEvent;
use Lochmueller\Index\Event\IndexPageEvent;
use Lochmueller\Index\Event\StartIndexProcessEvent;
use Lochmueller\Index\EventListener\LogIndexEventListener;
use Lochmueller\Index\Tests\Unit\AbstractTest;
use Psr\Log\LoggerInterface;
use TYPO3\CMS\Core\Site\Entity\Site;

class LogIndexEventListenerTest extends AbstractTest
{
    protected function tearDown(): void
    {
        parent::tearDown();
        unset($GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['index']);
    }

    public function testLoggerReceivesDebugMessageForStartIndexProcessEvent(): void
    {
        $siteStub = $this->createStub(Site::class);
        $siteStub->method('getIdentifier')->willReturn('test-site');

        $event = new StartIndexProcessEvent(
            site: $siteStub,
            technology: IndexTechnology::Database,
            type: IndexType::Full,
            indexConfigurationRecordId: 1,
            indexProcessId: 'test-process-123',
            startTime: 1234567890.0,
        );

        $loggerMock = $this->createMock(LoggerInterface::class);
        $loggerMock->expects(self::once())
            ->method('debug')
            ->with(
                'Execute ' . StartIndexProcessEvent::class,
                [
                    'site' => 'test-site',
                    'technology' => 'database',
                ]
            );

        $subject = new LogIndexEventListener();
        $subject->setLogger($loggerMock);

        // Will fail on database access, but logger should be called first
        try {
            $subject($event);
        } catch (\Exception) {
            // Expected - database not available in unit tests
        }
    }

    public function testLoggerReceivesDebugMessageForIndexPageEvent(): void
    {
        $siteStub = $this->createStub(Site::class);
        $siteStub->method('getIdentifier')->willReturn('page-site');

        $event = new IndexPageEvent(
            site: $siteStub,
            technology: IndexTechnology::Frontend,
            type: IndexType::Partial,
            indexConfigurationRecordId: 2,
            indexProcessId: 'page-process-456',
            language: 0,
            title: 'Test Page',
            content: 'Test content',
            pageUid: 42,
            accessGroups: [0, -1],
            uri: 'https://example.com/test',
        );

        $loggerMock = $this->createMock(LoggerInterface::class);
        $loggerMock->expects(self::once())
            ->method('debug')
            ->with(
                'Execute ' . IndexPageEvent::class,
                [
                    'site' => 'page-site',
                    'technology' => 'frontend',
                ]
            );

        $subject = new LogIndexEventListener();
        $subject->setLogger($loggerMock);

        try {
            $subject($event);
        } catch (\Exception) {
            // Expected - database not available in unit tests
        }
    }

    public function testLoggerReceivesDebugMessageForIndexFileEvent(): void
    {
        $siteStub = $this->createStub(Site::class);
        $siteStub->method('getIdentifier')->willReturn('file-site');

        $event = new IndexFileEvent(
            site: $siteStub,
            indexConfigurationRecordId: 3,
            indexProcessId: 'file-process-789',
            title: 'Test File',
            content: 'File content',
            fileIdentifier: '1:/test.pdf',
            uri: 'https://example.com/test.pdf',
        );

        $loggerMock = $this->createMock(LoggerInterface::class);
        $loggerMock->expects(self::once())
            ->method('debug')
            ->with(
                'Execute ' . IndexFileEvent::class,
                [
                    'site' => 'file-site',
                    'technology' => null,
                ]
            );

        $subject = new LogIndexEventListener();
        $subject->setLogger($loggerMock);

        try {
            $subject($event);
        } catch (\Exception) {
            // Expected - database not available in unit tests
        }
    }

    public function testLoggerReceivesDebugMessageForFinishIndexProcessEvent(): void
    {
        $siteStub = $this->createStub(Site::class);
        $siteStub->method('getIdentifier')->willReturn('finish-site');

        $event = new FinishIndexProcessEvent(
            site: $siteStub,
            technology: IndexTechnology::Http,
            type: IndexType::Full,
            indexConfigurationRecordId: 4,
            indexProcessId: 'finish-process-000',
            endTime: 9876543210.0,
        );

        $loggerMock = $this->createMock(LoggerInterface::class);
        $loggerMock->expects(self::once())
            ->method('debug')
            ->with(
                'Execute ' . FinishIndexProcessEvent::class,
                [
                    'site' => 'finish-site',
                    'technology' => 'http',
                ]
            );

        $subject = new LogIndexEventListener();
        $subject->setLogger($loggerMock);

        try {
            $subject($event);
        } catch (\Exception) {
            // Expected - database not available in unit tests
        }
    }

    public function testLoggerWorksWithNullLogger(): void
    {
        $siteStub = $this->createStub(Site::class);

        $event = new StartIndexProcessEvent(
            site: $siteStub,
            technology: IndexTechnology::Database,
            type: IndexType::Full,
            indexConfigurationRecordId: 1,
            indexProcessId: 'null-logger-test',
            startTime: 1234567890.0,
        );

        $subject = new LogIndexEventListener();
        // No logger set - should not throw

        try {
            $subject($event);
        } catch (\Exception) {
            // Expected - database not available, but no exception from null logger
        }

        // If we get here without a fatal error from null logger access, test passes
        self::assertTrue(true);
    }
}
