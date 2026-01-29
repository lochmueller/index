<?php

declare(strict_types=1);

namespace Lochmueller\Index\Tests\Unit\Traversing;

use Lochmueller\Index\Domain\Repository\GenericRepository;
use Lochmueller\Index\Tests\Unit\AbstractTest;
use Lochmueller\Index\Traversing\FileTraversing;
use TYPO3\CMS\Core\Resource\File;
use TYPO3\CMS\Core\Resource\FileInterface;
use TYPO3\CMS\Core\Resource\Folder;
use TYPO3\CMS\Core\Resource\ResourceFactory;

class FileTraversingTest extends AbstractTest
{
    public function testClassCanBeInstantiated(): void
    {
        $resourceFactory = $this->createStub(ResourceFactory::class);
        $genericRepository = $this->createStub(GenericRepository::class);
        $subject = new FileTraversing($resourceFactory, $genericRepository);

        self::assertInstanceOf(FileTraversing::class, $subject);
    }

    public function testGetFileByCompinedIdentifierReturnsFileInterface(): void
    {
        $combinedIdentifier = '1:/user_upload/test.pdf';
        $expectedFile = $this->createStub(FileInterface::class);

        $resourceFactory = $this->createStub(ResourceFactory::class);
        $resourceFactory->method('getFileObjectFromCombinedIdentifier')
            ->with($combinedIdentifier)
            ->willReturn($expectedFile);

        $genericRepository = $this->createStub(GenericRepository::class);
        $subject = new FileTraversing($resourceFactory, $genericRepository);

        $result = $subject->getFileByCompinedIdentifier($combinedIdentifier);

        self::assertSame($expectedFile, $result);
    }

    public function testGetFileByCompinedIdentifierReturnsNullWhenFileNotFound(): void
    {
        $combinedIdentifier = '1:/non_existent/file.pdf';

        $resourceFactory = $this->createStub(ResourceFactory::class);
        $resourceFactory->method('getFileObjectFromCombinedIdentifier')
            ->with($combinedIdentifier)
            ->willReturn(null);

        $genericRepository = $this->createStub(GenericRepository::class);
        $subject = new FileTraversing($resourceFactory, $genericRepository);

        $result = $subject->getFileByCompinedIdentifier($combinedIdentifier);

        self::assertNull($result);
    }

    public function testFindFilesInFileMountUidRecursiveReturnsEmptyWhenFileMountNotFound(): void
    {
        $fileMountUid = 999;

        $genericRepository = $this->createStub(GenericRepository::class);
        $genericRepository->method('setTableName')->willReturnSelf();
        $genericRepository->method('findByUid')->with($fileMountUid)->willReturn(null);

        $resourceFactory = $this->createStub(ResourceFactory::class);
        $subject = new FileTraversing($resourceFactory, $genericRepository);

        $result = iterator_to_array($subject->findFilesInFileMountUidRecursive($fileMountUid, ['pdf']));

        self::assertSame([], $result);
    }

    public function testFindFilesInFileMountUidRecursiveYieldsMatchingFiles(): void
    {
        $fileMountUid = 1;
        $fileExtensions = ['pdf', 'docx'];
        $fileMountRow = ['identifier' => '1:/documents/'];

        $pdfFile = $this->createStub(File::class);
        $pdfFile->method('getExtension')->willReturn('pdf');

        $docxFile = $this->createStub(File::class);
        $docxFile->method('getExtension')->willReturn('docx');

        $txtFile = $this->createStub(File::class);
        $txtFile->method('getExtension')->willReturn('txt');

        $folder = $this->createStub(Folder::class);
        $folder->method('getFiles')->willReturn([$pdfFile, $docxFile, $txtFile]);

        $genericRepository = $this->createStub(GenericRepository::class);
        $genericRepository->method('setTableName')->with('sys_filemounts')->willReturnSelf();
        $genericRepository->method('findByUid')->with($fileMountUid)->willReturn($fileMountRow);

        $resourceFactory = $this->createStub(ResourceFactory::class);
        $resourceFactory->method('getFolderObjectFromCombinedIdentifier')
            ->with($fileMountRow['identifier'])
            ->willReturn($folder);

        $subject = new FileTraversing($resourceFactory, $genericRepository);

        $result = iterator_to_array($subject->findFilesInFileMountUidRecursive($fileMountUid, $fileExtensions));

        self::assertCount(2, $result);
        self::assertSame($pdfFile, $result[0]);
        self::assertSame($docxFile, $result[1]);
    }

    public function testFindFilesInFileMountUidRecursiveYieldsNoFilesWhenExtensionsDontMatch(): void
    {
        $fileMountUid = 1;
        $fileExtensions = ['xlsx'];
        $fileMountRow = ['identifier' => '1:/documents/'];

        $pdfFile = $this->createStub(File::class);
        $pdfFile->method('getExtension')->willReturn('pdf');

        $folder = $this->createStub(Folder::class);
        $folder->method('getFiles')->willReturn([$pdfFile]);

        $genericRepository = $this->createStub(GenericRepository::class);
        $genericRepository->method('setTableName')->willReturnSelf();
        $genericRepository->method('findByUid')->willReturn($fileMountRow);

        $resourceFactory = $this->createStub(ResourceFactory::class);
        $resourceFactory->method('getFolderObjectFromCombinedIdentifier')->willReturn($folder);

        $subject = new FileTraversing($resourceFactory, $genericRepository);

        $result = iterator_to_array($subject->findFilesInFileMountUidRecursive($fileMountUid, $fileExtensions));

        self::assertSame([], $result);
    }

    public function testFindFilesInFileMountUidRecursiveYieldsEmptyWhenFolderIsEmpty(): void
    {
        $fileMountUid = 1;
        $fileMountRow = ['identifier' => '1:/empty_folder/'];

        $folder = $this->createStub(Folder::class);
        $folder->method('getFiles')->willReturn([]);

        $genericRepository = $this->createStub(GenericRepository::class);
        $genericRepository->method('setTableName')->willReturnSelf();
        $genericRepository->method('findByUid')->willReturn($fileMountRow);

        $resourceFactory = $this->createStub(ResourceFactory::class);
        $resourceFactory->method('getFolderObjectFromCombinedIdentifier')->willReturn($folder);

        $subject = new FileTraversing($resourceFactory, $genericRepository);

        $result = iterator_to_array($subject->findFilesInFileMountUidRecursive($fileMountUid, ['pdf']));

        self::assertSame([], $result);
    }
}
