<?php

declare(strict_types=1);

namespace Lochmueller\Index\Tests\Unit\FileExtraction\Extractor;

use Lochmueller\Index\FileExtraction\Extractor\TextFileExtraction;
use Lochmueller\Index\Tests\Unit\AbstractTest;
use TYPO3\CMS\Core\Resource\FileInterface;

class TextFileExtractionTest extends AbstractTest
{
    public function testGetFileGroupNameReturnsTxt(): void
    {
        $subject = new TextFileExtraction();

        self::assertSame('txt', $subject->getFileGroupName());
    }

    public function testGetFileGroupLabelReturnsTxt(): void
    {
        $subject = new TextFileExtraction();

        self::assertSame('Txt', $subject->getFileGroupLabel());
    }

    public function testGetFileGroupIconIdentifierReturnsCorrectIcon(): void
    {
        $subject = new TextFileExtraction();

        self::assertSame('mimetypes-text-text', $subject->getFileGroupIconIdentifier());
    }

    public function testGetFileExtensionsReturnsTxtExtension(): void
    {
        $subject = new TextFileExtraction();

        self::assertSame(['txt'], $subject->getFileExtensions());
    }

    public function testGetFileContentReturnsFileContents(): void
    {
        $file = $this->createStub(FileInterface::class);
        $file->method('getContents')->willReturn('Test file content');

        $subject = new TextFileExtraction();
        $result = $subject->getFileContent($file);

        self::assertSame('Test file content', $result);
    }
}
