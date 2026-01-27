<?php

declare(strict_types=1);

namespace Lochmueller\Index\Tests\Unit\FileExtraction\Extractor;

use Lochmueller\Index\FileExtraction\Extractor\ExcelFileExtraction;
use Lochmueller\Index\Tests\Unit\AbstractTest;

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
}
