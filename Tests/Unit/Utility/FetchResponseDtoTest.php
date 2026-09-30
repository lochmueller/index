<?php

declare(strict_types=1);

namespace Lochmueller\Index\Tests\Unit\Utility;

use Lochmueller\Index\Tests\Unit\AbstractTest;
use Lochmueller\Index\Utility\FetchResponseDto;

class FetchResponseDtoTest extends AbstractTest
{
    public function testIsHtmlByContentType(): void
    {
        self::assertTrue((new FetchResponseDto('https://example.com/', 'text/html; charset=utf-8', ''))->isHtml());
        self::assertTrue((new FetchResponseDto('https://example.com/', 'application/xhtml+xml', ''))->isHtml());
        self::assertFalse((new FetchResponseDto('https://example.com/', 'application/pdf', '<html>'))->isHtml());
    }

    public function testIsHtmlByContentWithoutContentType(): void
    {
        self::assertTrue((new FetchResponseDto('https://example.com/', '', '<!DOCTYPE html><html lang="en"></html>'))->isHtml());
        self::assertFalse((new FetchResponseDto('https://example.com/', '', 'plain text'))->isHtml());
    }
}
