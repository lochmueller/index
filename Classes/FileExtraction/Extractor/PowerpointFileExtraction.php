<?php

declare(strict_types=1);

namespace Lochmueller\Index\FileExtraction\Extractor;

use PhpOffice\PhpPresentation\IOFactory;
use PhpOffice\PhpPresentation\Shape\RichText;
use TYPO3\CMS\Core\Resource\FileInterface;

class PowerpointFileExtraction implements FileExtractionInterface
{
    public function getFileGroupName(): string
    {
        return 'powerpoint';
    }

    public function getFileGroupLabel(): string
    {
        return 'Powerpoint';
    }

    public function getFileGroupIconIdentifier(): string
    {
        return 'mimetypes-powerpoint';
    }

    /**
     * @return string[]
     */
    public function getFileExtensions(): array
    {
        return ['pps', 'ppsx', 'ppt', 'pptm', 'pptx', 'potm', 'potx'];
    }

    public function getFileContent(FileInterface $file): string
    {
        !class_exists(IOFactory::class) || throw new \RuntimeException('Package phpoffice/phppresentation is not installed. Please execute "composer require phpoffice/phppresentation"', 1263781);

        $phpPowerpoint = IOFactory::load($file->getForLocalProcessing(false));
        $text = '';
        foreach ($phpPowerpoint->getAllSlides() as $slide) {
            foreach ($slide->getShapeCollection() as $shape) {
                if ($shape instanceof RichText) {
                    $text .= $shape->getPlainText() . "\n";
                }
            }
        }
        return $text;
    }

}
