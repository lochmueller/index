<?php

declare(strict_types=1);

namespace Lochmueller\Index\Tests\Unit\FileExtraction\Extractor;

use Lochmueller\Index\FileExtraction\Extractor\PowerpointFileExtraction;
use Lochmueller\Index\Tests\Unit\AbstractTest;
use PhpOffice\PhpPresentation\IOFactory;
use TYPO3\CMS\Core\Resource\FileInterface;

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

    public function testGetFileExtensionsContainsAllExpectedExtensions(): void
    {
        $subject = new PowerpointFileExtraction();
        $extensions = $subject->getFileExtensions();

        $expectedExtensions = ['pps', 'ppsx', 'ppt', 'pptm', 'pptx', 'potm', 'potx'];
        self::assertSame($expectedExtensions, $extensions);
    }

    public function testGetFileContentExtractsTextFromPowerpoint(): void
    {
        if (!class_exists(IOFactory::class)) {
            self::markTestSkipped('Package phpoffice/phppresentation is not installed');
        }

        $pptPath = __DIR__ . '/Fixtures/test.pptx';
        if (!file_exists($pptPath)) {
            self::markTestSkipped('Test Powerpoint file not available');
        }

        $file = $this->createStub(FileInterface::class);
        $file->method('getForLocalProcessing')->willReturn($pptPath);

        $subject = new PowerpointFileExtraction();
        $result = $subject->getFileContent($file);

        self::assertIsString($result);
    }

    public function testGetFileContentThrowsExceptionWhenPhpPresentationNotInstalled(): void
    {
        if (class_exists(IOFactory::class)) {
            self::markTestSkipped('Test only runs when phpoffice/phppresentation is not installed');
        }

        $file = $this->createStub(FileInterface::class);
        $subject = new PowerpointFileExtraction();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Package phpoffice/phppresentation is not installed');

        $subject->getFileContent($file);
    }
}
