<?php

declare(strict_types=1);

namespace Lochmueller\Index\Tests\Unit\FileExtraction\Extractor;

use Lochmueller\Index\FileExtraction\Extractor\ExcelFileExtraction;
use Lochmueller\Index\Tests\Unit\AbstractTest;
use PhpOffice\PhpSpreadsheet\IOFactory;
use TYPO3\CMS\Core\Resource\FileInterface;

class ExcelFileExtractionTest extends AbstractTest
{
    public function testGetFileGroupNameReturnsExcel(): void
    {
        $subject = new ExcelFileExtraction();

        self::assertSame('excel', $subject->getFileGroupName());
    }

    public function testGetFileGroupLabelReturnsExcel(): void
    {
        $subject = new ExcelFileExtraction();

        self::assertSame('Excel', $subject->getFileGroupLabel());
    }

    public function testGetFileGroupIconIdentifierReturnsCorrectIcon(): void
    {
        $subject = new ExcelFileExtraction();

        self::assertSame('mimetypes-excel', $subject->getFileGroupIconIdentifier());
    }

    public function testGetFileExtensionsReturnsExcelExtensions(): void
    {
        $subject = new ExcelFileExtraction();
        $extensions = $subject->getFileExtensions();

        self::assertContains('xls', $extensions);
        self::assertContains('xlsx', $extensions);
        self::assertContains('xlsm', $extensions);
    }

    public function testGetFileExtensionsContainsAllExpectedExtensions(): void
    {
        $subject = new ExcelFileExtraction();
        $extensions = $subject->getFileExtensions();

        $expectedExtensions = ['xls', 'xlsm', 'xlsx', 'xltm', 'xltx', 'sxc'];
        self::assertSame($expectedExtensions, $extensions);
    }

    public function testGetFileContentExtractsTextFromExcel(): void
    {
        if (!class_exists(IOFactory::class)) {
            self::markTestSkipped('Package phpoffice/phpspreadsheet is not installed');
        }

        $excelPath = __DIR__ . '/Fixtures/test.xlsx';
        if (!file_exists($excelPath)) {
            self::markTestSkipped('Test Excel file not available');
        }

        $file = $this->createStub(FileInterface::class);
        $file->method('getForLocalProcessing')->willReturn($excelPath);

        $subject = new ExcelFileExtraction();
        $result = $subject->getFileContent($file);

        self::assertIsString($result);
    }

    public function testGetFileContentThrowsExceptionWhenPhpSpreadsheetNotInstalled(): void
    {
        if (class_exists(IOFactory::class)) {
            self::markTestSkipped('Test only runs when phpoffice/phpspreadsheet is not installed');
        }

        $file = $this->createStub(FileInterface::class);
        $subject = new ExcelFileExtraction();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Package phpoffice/phpspreadsheet is not installed');

        $subject->getFileContent($file);
    }
}
