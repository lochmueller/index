<?php

declare(strict_types=1);

namespace Lochmueller\Index\FileExtraction\Extractor;

use PhpOffice\PhpSpreadsheet\IOFactory;
use TYPO3\CMS\Core\Resource\FileInterface;

class ExcelFileExtraction implements FileExtractionInterface
{
    public function getFileGroupName(): string
    {
        return 'excel';
    }

    public function getFileGroupLabel(): string
    {
        return 'Excel';
    }

    public function getFileGroupIconIdentifier(): string
    {
        return 'mimetypes-excel';
    }

    public function getFileExtensions(): array
    {
        return ['xls', 'xlsm', 'xlsx', 'xltm', 'xltx', 'sxc',];
    }

    public function getFileContent(FileInterface $file): string
    {
        if (!class_exists(IOFactory::class)) {
            throw new \RuntimeException('Package phpoffice/phpspreadsheet is not installed. Please execute "composer require phpoffice/phpspreadsheet"', 1263781);
        }
        $phpSpreadsheet = IOFactory::load($file->getForLocalProcessing(false));
        $text = '';

        foreach ($phpSpreadsheet->getWorksheetIterator() as $sheet) {
            $text .= $sheet->getTitle() . PHP_EOL;
            $rows = $sheet->toArray(null, true, false, false);
            foreach ($rows as $rIdx => $row) {
                $text .= "$rIdx: " . implode(" | ", array_map(fn($v) => (string) $v, $row)) . PHP_EOL;
            }
        }
        return $text;
    }

}
