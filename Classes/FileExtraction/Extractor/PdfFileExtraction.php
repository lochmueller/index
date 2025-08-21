<?php

declare(strict_types=1);

namespace Lochmueller\Index\FileExtraction\Extractor;

use Smalot\PdfParser\Parser;
use TYPO3\CMS\Core\Resource\FileInterface;

class PdfFileExtraction implements FileExtractionInterface
{
    public function getFileGroupName(): string
    {
        return 'pdf';
    }

    public function getFileGroupLabel(): string
    {
        return 'PDF';
    }

    public function getFileGroupIconIdentifier(): string
    {
        return 'mimetypes-pdf';
    }

    public function getFileExtensions(): array
    {
        return ['pdf'];
    }

    public function getFileContent(FileInterface $file): string
    {
        !class_exists(Parser::class) || throw new \RuntimeException('Package smalot/pdfparser is not installed. Please execute composer require smalot/pdfparser', 1263781);

        $parser = new Parser();
        $pdf = $parser->parseFile($file->getForLocalProcessing(false));

        return $pdf->getText();
    }

}
