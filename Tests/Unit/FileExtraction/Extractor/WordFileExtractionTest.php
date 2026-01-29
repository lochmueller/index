<?php

declare(strict_types=1);

namespace Lochmueller\Index\Tests\Unit\FileExtraction\Extractor;

use Lochmueller\Index\FileExtraction\Extractor\WordFileExtraction;
use Lochmueller\Index\Tests\Unit\AbstractTest;
use PhpOffice\PhpWord\IOFactory;
use TYPO3\CMS\Core\Resource\FileInterface;

class WordFileExtractionTest extends AbstractTest
{
    public function testGetFileGroupNameReturnsWord(): void
    {
        $subject = new WordFileExtraction();

        self::assertSame('word', $subject->getFileGroupName());
    }

    public function testGetFileGroupLabelReturnsWord(): void
    {
        $subject = new WordFileExtraction();

        self::assertSame('Word', $subject->getFileGroupLabel());
    }

    public function testGetFileGroupIconIdentifierReturnsCorrectIcon(): void
    {
        $subject = new WordFileExtraction();

        self::assertSame('mimetypes-word', $subject->getFileGroupIconIdentifier());
    }

    public function testGetFileExtensionsReturnsWordExtensions(): void
    {
        $subject = new WordFileExtraction();
        $extensions = $subject->getFileExtensions();

        self::assertContains('doc', $extensions);
        self::assertContains('docx', $extensions);
        self::assertContains('rtf', $extensions);
    }

    public function testGetFileExtensionsContainsAllExpectedExtensions(): void
    {
        $subject = new WordFileExtraction();
        $extensions = $subject->getFileExtensions();

        $expectedExtensions = ['doc', 'dot', 'docm', 'docx', 'dotm', 'dotx', 'sxw', 'rtf'];
        self::assertSame($expectedExtensions, $extensions);
    }

    public function testGetFileContentExtractsTextFromWord(): void
    {
        if (!class_exists(IOFactory::class)) {
            self::markTestSkipped('Package phpoffice/phpword is not installed');
        }

        $wordPath = __DIR__ . '/Fixtures/test.docx';
        if (!file_exists($wordPath)) {
            self::markTestSkipped('Test Word file not available');
        }

        $file = $this->createStub(FileInterface::class);
        $file->method('getForLocalProcessing')->willReturn($wordPath);

        $subject = new WordFileExtraction();
        $result = $subject->getFileContent($file);

        self::assertIsString($result);
    }

    public function testGetFileContentThrowsExceptionWhenPhpWordNotInstalled(): void
    {
        if (class_exists(IOFactory::class)) {
            self::markTestSkipped('Test only runs when phpoffice/phpword is not installed');
        }

        $file = $this->createStub(FileInterface::class);
        $subject = new WordFileExtraction();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Package phpoffice/phpword is not installed');

        $subject->getFileContent($file);
    }
}
