<?php

declare(strict_types=1);

namespace Lochmueller\Index\Tests\Unit\FileExtraction\Extractor;

use Lochmueller\Index\FileExtraction\Extractor\WordFileExtraction;
use Lochmueller\Index\Tests\Unit\AbstractTest;
use PhpOffice\PhpWord\IOFactory;
use PHPUnit\Framework\Attributes\DataProvider;
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
        $file->method('getExtension')->willReturn('docx');
        $file->method('getForLocalProcessing')->willReturn($wordPath);

        $subject = new WordFileExtraction();
        $result = $subject->getFileContent($file);

        self::assertStringContainsString('Test Content', $result);
    }

    #[DataProvider('wordFiles')]
    public function testGetFileContentSelectsReaderForFileExtension(string $extension, string $fixture, string $expectedText): void
    {
        if (!class_exists(IOFactory::class)) {
            self::markTestSkipped('Package phpoffice/phpword is not installed');
        }

        $file = $this->createStub(FileInterface::class);
        $file->method('getExtension')->willReturn($extension);
        $file->method('getForLocalProcessing')->willReturn(__DIR__ . '/Fixtures/' . $fixture);

        $result = (new WordFileExtraction())->getFileContent($file);

        self::assertStringContainsString($expectedText, $result);
    }

    /**
     * @return array<string, array{string, string, string}>
     */
    public static function wordFiles(): array
    {
        $legacyText = 'Legacy Word document extraction test.';

        return [
            'RTF' => ['rtf', 'test.rtf', 'RTF document extraction test.'],
            'uppercase RTF' => ['RTF', 'test.rtf', 'RTF document extraction test.'],
            'Word 97-2003' => ['doc', 'test.doc', $legacyText],
            'uppercase Word 97-2003' => ['DOC', 'test.doc', $legacyText],
            'Word 97-2003 template' => ['dot', 'test.doc', $legacyText],
            'Word 2007' => ['docx', 'test.docx', 'Test Content'],
            'macro-enabled Word 2007' => ['docm', 'test.docx', 'Test Content'],
            'Word 2007 template' => ['dotx', 'test.docx', 'Test Content'],
            'macro-enabled Word 2007 template' => ['dotm', 'test.docx', 'Test Content'],
        ];
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
