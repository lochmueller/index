<?php

declare(strict_types=1);

namespace Lochmueller\Index\Tests\Unit\FileExtraction;

use Lochmueller\Index\FileExtraction\Extractor\FileExtractionInterface;
use Lochmueller\Index\FileExtraction\FileExtractor;
use Lochmueller\Index\Tests\Unit\AbstractTest;
use TYPO3\CMS\Core\Resource\FileInterface;

class FileExtractorTest extends AbstractTest
{
    public function testExtractReturnsNullWhenNoExtractorMatches(): void
    {
        $file = $this->createStub(FileInterface::class);
        $file->method('getExtension')->willReturn('unknown');

        $subject = new FileExtractor([]);
        $result = $subject->extract($file);

        self::assertNull($result);
    }

    public function testExtractReturnsContentFromMatchingExtractor(): void
    {
        $file = $this->createStub(FileInterface::class);
        $file->method('getExtension')->willReturn('txt');

        $extractor = $this->createStub(FileExtractionInterface::class);
        $extractor->method('getFileExtensions')->willReturn(['txt']);
        $extractor->method('getFileContent')->willReturn('File content');

        $subject = new FileExtractor([$extractor]);
        $result = $subject->extract($file);

        self::assertSame('File content', $result);
    }


    public function testExtractUsesFirstMatchingExtractor(): void
    {
        $file = $this->createStub(FileInterface::class);
        $file->method('getExtension')->willReturn('pdf');

        $extractor1 = $this->createStub(FileExtractionInterface::class);
        $extractor1->method('getFileExtensions')->willReturn(['pdf']);
        $extractor1->method('getFileContent')->willReturn('First extractor');

        $extractor2 = $this->createStub(FileExtractionInterface::class);
        $extractor2->method('getFileExtensions')->willReturn(['pdf']);
        $extractor2->method('getFileContent')->willReturn('Second extractor');

        $subject = new FileExtractor([$extractor1, $extractor2]);
        $result = $subject->extract($file);

        self::assertSame('First extractor', $result);
    }

    public function testResolveFileTypesReturnsExtensionsForMatchingGroups(): void
    {
        $extractor1 = $this->createStub(FileExtractionInterface::class);
        $extractor1->method('getFileGroupName')->willReturn('pdf');
        $extractor1->method('getFileExtensions')->willReturn(['pdf']);

        $extractor2 = $this->createStub(FileExtractionInterface::class);
        $extractor2->method('getFileGroupName')->willReturn('txt');
        $extractor2->method('getFileExtensions')->willReturn(['txt', 'text']);

        $subject = new FileExtractor([$extractor1, $extractor2]);
        $result = $subject->resolveFileTypes(['pdf', 'txt']);

        self::assertContains('pdf', $result);
        self::assertContains('txt', $result);
        self::assertContains('text', $result);
    }

    public function testResolveFileTypesReturnsEmptyArrayWhenNoMatch(): void
    {
        $extractor = $this->createStub(FileExtractionInterface::class);
        $extractor->method('getFileGroupName')->willReturn('pdf');
        $extractor->method('getFileExtensions')->willReturn(['pdf']);

        $subject = new FileExtractor([$extractor]);
        $result = $subject->resolveFileTypes(['unknown']);

        self::assertSame([], $result);
    }


    public function testResolveFileTypesReturnsUniqueExtensions(): void
    {
        $extractor1 = $this->createStub(FileExtractionInterface::class);
        $extractor1->method('getFileGroupName')->willReturn('group1');
        $extractor1->method('getFileExtensions')->willReturn(['txt', 'doc']);

        $extractor2 = $this->createStub(FileExtractionInterface::class);
        $extractor2->method('getFileGroupName')->willReturn('group2');
        $extractor2->method('getFileExtensions')->willReturn(['txt', 'pdf']);

        $subject = new FileExtractor([$extractor1, $extractor2]);
        $result = $subject->resolveFileTypes(['group1', 'group2']);

        self::assertCount(3, $result);
    }

    public function testGetBackendItemsPopulatesParams(): void
    {
        $extractor = $this->createStub(FileExtractionInterface::class);
        $extractor->method('getFileGroupLabel')->willReturn('PDF Files');
        $extractor->method('getFileGroupName')->willReturn('pdf');
        $extractor->method('getFileGroupIconIdentifier')->willReturn('mimetypes-pdf');

        $subject = new FileExtractor([$extractor]);
        $params = ['items' => []];
        $subject->getBackendItems($params);

        self::assertCount(1, $params['items']);
        self::assertSame('PDF Files', $params['items'][0]['label']);
        self::assertSame('pdf', $params['items'][0]['value']);
        self::assertSame('mimetypes-pdf', $params['items'][0]['icon']);
    }

    public function testGetBackendItemsWithMultipleExtractors(): void
    {
        $extractor1 = $this->createStub(FileExtractionInterface::class);
        $extractor1->method('getFileGroupLabel')->willReturn('PDF');
        $extractor1->method('getFileGroupName')->willReturn('pdf');
        $extractor1->method('getFileGroupIconIdentifier')->willReturn('mimetypes-pdf');

        $extractor2 = $this->createStub(FileExtractionInterface::class);
        $extractor2->method('getFileGroupLabel')->willReturn('Word');
        $extractor2->method('getFileGroupName')->willReturn('word');
        $extractor2->method('getFileGroupIconIdentifier')->willReturn('mimetypes-word');

        $subject = new FileExtractor([$extractor1, $extractor2]);
        $params = ['items' => []];
        $subject->getBackendItems($params);

        self::assertCount(2, $params['items']);
    }
}
