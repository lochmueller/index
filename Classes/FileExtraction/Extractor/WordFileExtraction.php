<?php

declare(strict_types=1);

namespace Lochmueller\Index\FileExtraction\Extractor;

use PhpOffice\PhpWord\IOFactory;
use TYPO3\CMS\Core\Resource\FileInterface;

class WordFileExtraction implements FileExtractionInterface
{
    public function getFileGroupName(): string
    {
        return 'word';
    }

    public function getFileGroupLabel(): string
    {
        return 'Word';
    }

    public function getFileGroupIconIdentifier(): string
    {
        return 'mimetypes-word';
    }

    public function getFileExtensions(): array
    {
        return ['doc', 'dot', 'docm', 'docx', 'dotm', 'dotx', 'sxw', 'rtf'];
    }

    public function getFileContent(FileInterface $file): string
    {
        if (!class_exists(IOFactory::class)) {
            throw new \RuntimeException('Package phpoffice/phpword is not installed. Please execute "composer require phpoffice/phpword"', 1263781);
        }
        $phpWord = IOFactory::load($file->getForLocalProcessing(false));
        $text = '';
        foreach ($phpWord->getSections() as $section) {
            foreach ($section->getElements() as $element) {
                if (method_exists($element, 'getText')) {
                    $text .= $element->getText() . "\n";
                }
            }
        }
        return $text;
    }

}
