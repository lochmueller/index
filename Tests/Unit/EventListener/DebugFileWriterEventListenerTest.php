<?php

declare(strict_types=1);

namespace Lochmueller\Index\Tests\Unit\EventListener;

use Lochmueller\Index\Enums\IndexTechnology;
use Lochmueller\Index\Enums\IndexType;
use Lochmueller\Index\Event\FinishIndexProcessEvent;
use Lochmueller\Index\Event\IndexFileEvent;
use Lochmueller\Index\Event\IndexPageEvent;
use Lochmueller\Index\Event\StartIndexProcessEvent;
use Lochmueller\Index\EventListener\DebugFileWriterEventListener;
use Lochmueller\Index\Tests\Unit\AbstractTest;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;
use TYPO3\CMS\Core\Attribute\AsEventListener;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Core\Site\Entity\SiteInterface;

class DebugFileWriterEventListenerTest extends AbstractTest
{
    protected function tearDown(): void
    {
        $debugDir = Environment::getVarPath() . '/index-debug';
        if (is_dir($debugDir)) {
            $this->removeDirectory($debugDir);
        }
        parent::tearDown();
    }

    private function removeDirectory(string $path): void
    {
        $items = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($path, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );
        foreach ($items as $item) {
            if ($item->isDir()) {
                rmdir($item->getPathname());
            } else {
                unlink($item->getPathname());
            }
        }
        rmdir($path);
    }

    private function createSiteStub(string $identifier = 'test-site'): SiteInterface
    {
        $siteStub = $this->createStub(SiteInterface::class);
        $siteStub->method('getIdentifier')->willReturn($identifier);

        return $siteStub;
    }

    private function createExtensionConfigurationStub(bool $enabled): ExtensionConfiguration
    {
        $stub = $this->createStub(ExtensionConfiguration::class);
        $stub->method('get')->willReturn($enabled ? '1' : '0');

        return $stub;
    }

    private function createExtensionConfigurationStubThrowing(): ExtensionConfiguration
    {
        $stub = $this->createStub(ExtensionConfiguration::class);
        $stub->method('get')->willThrowException(new \RuntimeException('Config not found'));

        return $stub;
    }

    private function createStartIndexProcessEvent(string $siteIdentifier = 'test-site', string $processId = 'process-123'): StartIndexProcessEvent
    {
        return new StartIndexProcessEvent(
            site: $this->createSiteStub($siteIdentifier),
            technology: IndexTechnology::Database,
            type: IndexType::Full,
            indexConfigurationRecordId: 1,
            indexProcessId: $processId,
            startTime: 1234567890.1234,
        );
    }

    private function createIndexPageEvent(string $siteIdentifier = 'test-site', string $processId = 'process-123'): IndexPageEvent
    {
        return new IndexPageEvent(
            site: $this->createSiteStub($siteIdentifier),
            technology: IndexTechnology::Frontend,
            type: IndexType::Partial,
            indexConfigurationRecordId: 42,
            indexProcessId: $processId,
            language: 0,
            title: 'Test Page',
            content: 'Test content',
            pageUid: 1,
            accessGroups: [0],
            uri: 'https://example.com/test',
        );
    }

    private function createIndexFileEvent(string $siteIdentifier = 'test-site', string $processId = 'process-123'): IndexFileEvent
    {
        return new IndexFileEvent(
            site: $this->createSiteStub($siteIdentifier),
            indexConfigurationRecordId: 10,
            indexProcessId: $processId,
            title: 'Test Document',
            content: 'Document content',
            fileIdentifier: '1:/documents/test.pdf',
            uri: 'https://example.com/documents/test.pdf',
        );
    }

    private function createFinishIndexProcessEvent(string $siteIdentifier = 'test-site', string $processId = 'process-123'): FinishIndexProcessEvent
    {
        return new FinishIndexProcessEvent(
            site: $this->createSiteStub($siteIdentifier),
            technology: IndexTechnology::Database,
            type: IndexType::Full,
            indexConfigurationRecordId: 1,
            indexProcessId: $processId,
            endTime: 1234567899.5678,
        );
    }

    public function testIsEnabledReturnsFalseWhenConfigurationIsMissing(): void
    {
        $extensionConfiguration = $this->createExtensionConfigurationStubThrowing();
        $subject = new DebugFileWriterEventListener($extensionConfiguration);

        $event = $this->createStartIndexProcessEvent();
        $subject($event);

        $debugDir = Environment::getVarPath() . '/index-debug/test-site/process-123';
        self::assertDirectoryDoesNotExist($debugDir);
    }

    public function testIsEnabledReturnsFalseWhenConfigurationIsDisabled(): void
    {
        $extensionConfiguration = $this->createExtensionConfigurationStub(false);
        $subject = new DebugFileWriterEventListener($extensionConfiguration);

        $event = $this->createStartIndexProcessEvent();
        $subject($event);

        $debugDir = Environment::getVarPath() . '/index-debug/test-site/process-123';
        self::assertDirectoryDoesNotExist($debugDir);
    }

    public function testIsEnabledReturnsTrueWhenConfigurationIsEnabled(): void
    {
        $extensionConfiguration = $this->createExtensionConfigurationStub(true);
        $subject = new DebugFileWriterEventListener($extensionConfiguration);

        $event = $this->createStartIndexProcessEvent();
        $subject($event);

        $debugDir = Environment::getVarPath() . '/index-debug/test-site/process-123';
        self::assertDirectoryExists($debugDir);
    }

    public function testInvokeDoesNotWriteFileWhenFeatureIsDisabled(): void
    {
        $extensionConfiguration = $this->createExtensionConfigurationStub(false);
        $subject = new DebugFileWriterEventListener($extensionConfiguration);

        $subject($this->createStartIndexProcessEvent());
        $subject($this->createIndexPageEvent());
        $subject($this->createIndexFileEvent());
        $subject($this->createFinishIndexProcessEvent());

        $debugDir = Environment::getVarPath() . '/index-debug';
        self::assertDirectoryDoesNotExist($debugDir);
    }

    public function testInvokeWritesFileForStartIndexProcessEvent(): void
    {
        $extensionConfiguration = $this->createExtensionConfigurationStub(true);
        $subject = new DebugFileWriterEventListener($extensionConfiguration);

        $event = $this->createStartIndexProcessEvent('main', 'abc-123');
        $subject($event);

        $dir = Environment::getVarPath() . '/index-debug/main/abc-123';
        self::assertDirectoryExists($dir);

        $files = glob($dir . '/StartIndexProcessEvent_*.txt');
        self::assertCount(1, $files);

        $content = json_decode(file_get_contents($files[0]), true);
        self::assertSame('StartIndexProcessEvent', $content['eventType']);
        self::assertSame('main', $content['site']);
        self::assertSame('database', $content['technology']);
        self::assertSame('full', $content['type']);
        self::assertSame(1, $content['indexConfigurationRecordId']);
        self::assertSame('abc-123', $content['indexProcessId']);
        self::assertArrayHasKey('startTime', $content);
    }

    public function testInvokeWritesFileForIndexPageEvent(): void
    {
        $extensionConfiguration = $this->createExtensionConfigurationStub(true);
        $subject = new DebugFileWriterEventListener($extensionConfiguration);

        $event = $this->createIndexPageEvent('main', 'abc-123');
        $subject($event);

        $dir = Environment::getVarPath() . '/index-debug/main/abc-123';
        $files = glob($dir . '/IndexPageEvent_*.txt');
        self::assertCount(1, $files);

        $content = json_decode(file_get_contents($files[0]), true);
        self::assertSame('IndexPageEvent', $content['eventType']);
        self::assertSame('main', $content['site']);
        self::assertSame('frontend', $content['technology']);
        self::assertSame('partial', $content['type']);
        self::assertSame(42, $content['indexConfigurationRecordId']);
        self::assertSame('abc-123', $content['indexProcessId']);
        self::assertSame(0, $content['language']);
        self::assertSame('Test Page', $content['title']);
        self::assertSame('Test content', $content['content']);
        self::assertSame(1, $content['pageUid']);
        self::assertSame([0], $content['accessGroups']);
        self::assertSame('https://example.com/test', $content['uri']);
    }

    public function testInvokeWritesFileForIndexFileEvent(): void
    {
        $extensionConfiguration = $this->createExtensionConfigurationStub(true);
        $subject = new DebugFileWriterEventListener($extensionConfiguration);

        $event = $this->createIndexFileEvent('main', 'abc-123');
        $subject($event);

        $dir = Environment::getVarPath() . '/index-debug/main/abc-123';
        $files = glob($dir . '/IndexFileEvent_*.txt');
        self::assertCount(1, $files);

        $content = json_decode(file_get_contents($files[0]), true);
        self::assertSame('IndexFileEvent', $content['eventType']);
        self::assertSame('main', $content['site']);
        self::assertSame(10, $content['indexConfigurationRecordId']);
        self::assertSame('abc-123', $content['indexProcessId']);
        self::assertSame('Test Document', $content['title']);
        self::assertSame('Document content', $content['content']);
        self::assertSame('1:/documents/test.pdf', $content['fileIdentifier']);
        self::assertSame('https://example.com/documents/test.pdf', $content['uri']);
    }

    public function testInvokeWritesFileForFinishIndexProcessEvent(): void
    {
        $extensionConfiguration = $this->createExtensionConfigurationStub(true);
        $subject = new DebugFileWriterEventListener($extensionConfiguration);

        $event = $this->createFinishIndexProcessEvent('main', 'abc-123');
        $subject($event);

        $dir = Environment::getVarPath() . '/index-debug/main/abc-123';
        $files = glob($dir . '/FinishIndexProcessEvent_*.txt');
        self::assertCount(1, $files);

        $content = json_decode(file_get_contents($files[0]), true);
        self::assertSame('FinishIndexProcessEvent', $content['eventType']);
        self::assertSame('main', $content['site']);
        self::assertSame('database', $content['technology']);
        self::assertSame('full', $content['type']);
        self::assertSame(1, $content['indexConfigurationRecordId']);
        self::assertSame('abc-123', $content['indexProcessId']);
        self::assertArrayHasKey('endTime', $content);
    }

    public function testAsEventListenerAttributeIsCorrectlySet(): void
    {
        $reflectionMethod = new \ReflectionMethod(DebugFileWriterEventListener::class, '__invoke');
        $attributes = $reflectionMethod->getAttributes(AsEventListener::class);

        self::assertCount(1, $attributes);

        $instance = $attributes[0]->newInstance();
        self::assertSame('index-debug-file-writer', $instance->identifier);
    }

    public function testErrorHandlingLogsWarningAndDoesNotPropagateException(): void
    {
        $extensionConfiguration = $this->createExtensionConfigurationStub(true);
        $subject = new DebugFileWriterEventListener($extensionConfiguration);

        /** @var LoggerInterface&MockObject $loggerMock */
        $loggerMock = $this->createMock(LoggerInterface::class);
        $loggerMock->expects(self::once())
            ->method('warning')
            ->with(
                self::stringContains('Debug file writer failed'),
                self::arrayHasKey('exception'),
            );
        $subject->setLogger($loggerMock);

        // Use a site identifier with null bytes to force a filesystem error
        $siteStub = $this->createStub(SiteInterface::class);
        $siteStub->method('getIdentifier')->willReturn("invalid\0path");

        $event = new StartIndexProcessEvent(
            site: $siteStub,
            technology: IndexTechnology::Database,
            type: IndexType::Full,
            indexConfigurationRecordId: 1,
            indexProcessId: 'error-process',
            startTime: 1234567890.0,
        );

        // Should not throw — error is caught and logged
        $subject($event);
    }

    public function testJsonOutputIsPrettyPrintedWithUnescapedUnicodeAndSlashes(): void
    {
        $extensionConfiguration = $this->createExtensionConfigurationStub(true);
        $subject = new DebugFileWriterEventListener($extensionConfiguration);

        $event = new IndexPageEvent(
            site: $this->createSiteStub('main'),
            technology: IndexTechnology::Frontend,
            type: IndexType::Partial,
            indexConfigurationRecordId: 1,
            indexProcessId: 'json-test',
            language: 0,
            title: 'Ünïcödé Tëst',
            content: 'path/to/file',
            pageUid: 1,
            accessGroups: [0],
            uri: 'https://example.com/path/to/page',
        );

        $subject($event);

        $dir = Environment::getVarPath() . '/index-debug/main/json-test';
        $files = glob($dir . '/IndexPageEvent_*.txt');
        self::assertCount(1, $files);

        $rawContent = file_get_contents($files[0]);

        // Pretty print: contains newlines and indentation
        self::assertStringContainsString("\n", $rawContent);
        self::assertStringContainsString('    ', $rawContent);

        // Unescaped unicode
        self::assertStringContainsString('Ünïcödé', $rawContent);

        // Unescaped slashes
        self::assertStringContainsString('https://example.com/path/to/page', $rawContent);
    }
}
