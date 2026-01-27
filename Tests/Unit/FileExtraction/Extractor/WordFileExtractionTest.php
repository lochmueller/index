<?php

declare(strict_types=1);

namespace Lochmueller\Index\Tests\Unit\FileExtraction\Extractor;

use Lochmueller\Index\FileExtraction\Extractor\WordFileExtraction;
use Lochmueller\Index\Tests\Unit\AbstractTest;

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
}
