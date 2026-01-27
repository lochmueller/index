<?php

declare(strict_types=1);

namespace Lochmueller\Index\Tests\Unit\Event\Extractor;

use Lochmueller\Index\Event\Extractor\CustomFileExtraction;
use Lochmueller\Index\Tests\Unit\AbstractTest;
use TYPO3\CMS\Core\Resource\FileInterface;

class CustomFileExtractionTest extends AbstractTest
{
    public function testConstructorWithDefaultValues(): void
    {
        $subject = new CustomFileExtraction();

        self::assertNull($subject->content);
        self::assertSame([], $subject->extensions);
    }

    public function testConstructorWithFile(): void
    {
        $file = $this->createStub(FileInterface::class);

        $subject = new CustomFileExtraction(file: $file);

        self::assertNull($subject->content);
        self::assertSame([], $subject->extensions);
    }

    public function testConstructorWithAllParameters(): void
    {
        $file = $this->createStub(FileInterface::class);
        $extensions = ['pdf', 'doc', 'txt'];

        $subject = new CustomFileExtraction(
            file: $file,
            content: 'Extracted content',
            extensions: $extensions,
        );

        self::assertSame('Extracted content', $subject->content);
        self::assertSame($extensions, $subject->extensions);
    }

    public function testContentCanBeModified(): void
    {
        $subject = new CustomFileExtraction();

        $subject->content = 'New content';

        self::assertSame('New content', $subject->content);
    }

    public function testExtensionsCanBeModified(): void
    {
        $subject = new CustomFileExtraction();

        $subject->extensions = ['xml', 'json'];

        self::assertSame(['xml', 'json'], $subject->extensions);
    }
}
