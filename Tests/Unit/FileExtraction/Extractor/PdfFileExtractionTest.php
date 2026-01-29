<?php

declare(strict_types=1);

namespace Lochmueller\Index\Tests\Unit\FileExtraction\Extractor;

use Lochmueller\Index\FileExtraction\Extractor\PdfFileExtraction;
use Lochmueller\Index\Tests\Unit\AbstractTest;
use TYPO3\CMS\Core\Resource\FileInterface;

class PdfFileExtractionTest extends AbstractTest
{
    public function testGetFileGroupNameReturnsPdf(): void
    {
        $subject = new PdfFileExtraction();

        self::assertSame('pdf', $subject->getFileGroupName());
    }

    public function testGetFileGroupLabelReturnsPdf(): void
    {
        $subject = new PdfFileExtraction();

        self::assertSame('PDF', $subject->getFileGroupLabel());
    }

    public function testGetFileGroupIconIdentifierReturnsCorrectIcon(): void
    {
        $subject = new PdfFileExtraction();

        self::assertSame('mimetypes-pdf', $subject->getFileGroupIconIdentifier());
    }

    public function testGetFileExtensionsReturnsPdfExtension(): void
    {
        $subject = new PdfFileExtraction();

        self::assertSame(['pdf'], $subject->getFileExtensions());
    }

    public function testGetFileExtensionsContainsOnlyPdf(): void
    {
        $subject = new PdfFileExtraction();
        $extensions = $subject->getFileExtensions();

        self::assertCount(1, $extensions);
        self::assertContains('pdf', $extensions);
    }

    public function testGetFileContentExtractsTextFromPdf(): void
    {
        if (!class_exists(\Smalot\PdfParser\Parser::class)) {
            self::markTestSkipped('Package smalot/pdfparser is not installed');
        }

        $pdfPath = __DIR__ . '/Fixtures/test.pdf';
        if (!file_exists($pdfPath)) {
            self::markTestSkipped('Test PDF file not available');
        }

        $file = $this->createStub(FileInterface::class);
        $file->method('getForLocalProcessing')->willReturn($pdfPath);

        $subject = new PdfFileExtraction();
        $result = $subject->getFileContent($file);

        self::assertIsString($result);
    }

    public function testGetFileContentThrowsExceptionWhenParserNotInstalled(): void
    {
        if (class_exists(\Smalot\PdfParser\Parser::class)) {
            self::markTestSkipped('Test only runs when smalot/pdfparser is not installed');
        }

        $file = $this->createStub(FileInterface::class);
        $subject = new PdfFileExtraction();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Package smalot/pdfparser is not installed');

        $subject->getFileContent($file);
    }
}
