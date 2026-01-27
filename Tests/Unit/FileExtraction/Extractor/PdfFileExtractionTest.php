<?php

declare(strict_types=1);

namespace Lochmueller\Index\Tests\Unit\FileExtraction\Extractor;

use Lochmueller\Index\FileExtraction\Extractor\PdfFileExtraction;
use Lochmueller\Index\Tests\Unit\AbstractTest;

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
}
