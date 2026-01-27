<?php

declare(strict_types=1);

namespace Lochmueller\Index\Tests\Unit\FileExtraction\Extractor;

use Lochmueller\Index\FileExtraction\Extractor\PowerpointFileExtraction;
use Lochmueller\Index\Tests\Unit\AbstractTest;

class PowerpointFileExtractionTest extends AbstractTest
{
    public function testGetFileGroupNameReturnsPowerpoint(): void
    {
        $subject = new PowerpointFileExtraction();

        self::assertSame('powerpoint', $subject->getFileGroupName());
    }

    public function testGetFileGroupLabelReturnsPowerpoint(): void
    {
        $subject = new PowerpointFileExtraction();

        self::assertSame('Powerpoint', $subject->getFileGroupLabel());
    }

    public function testGetFileGroupIconIdentifierReturnsCorrectIcon(): void
    {
        $subject = new PowerpointFileExtraction();

        self::assertSame('mimetypes-powerpoint', $subject->getFileGroupIconIdentifier());
    }

    public function testGetFileExtensionsReturnsPowerpointExtensions(): void
    {
        $subject = new PowerpointFileExtraction();
        $extensions = $subject->getFileExtensions();

        self::assertContains('ppt', $extensions);
        self::assertContains('pptx', $extensions);
        self::assertContains('pps', $extensions);
    }
}
