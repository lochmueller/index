<?php

declare(strict_types=1);

namespace Lochmueller\Index\Tests\Unit\Indexing\Database;

use Lochmueller\Index\Indexing\Database\DatabaseIndexingDto;
use Lochmueller\Index\Tests\Unit\AbstractTest;
use TYPO3\CMS\Core\Site\Entity\Site;

class DatabaseIndexingDtoTest extends AbstractTest
{
    public function testConstructorSetsAllProperties(): void
    {
        $site = $this->createStub(Site::class);
        $arguments = ['key' => 'value'];

        $dto = new DatabaseIndexingDto(
            title: 'Test Title',
            content: 'Test Content',
            pageUid: 42,
            languageUid: 1,
            arguments: $arguments,
            site: $site,
        );

        self::assertSame('Test Title', $dto->title);
        self::assertSame('Test Content', $dto->content);
        self::assertSame(42, $dto->pageUid);
        self::assertSame(1, $dto->languageUid);
        self::assertSame($arguments, $dto->arguments);
        self::assertSame($site, $dto->site);
    }

    public function testTitleAndContentAreMutable(): void
    {
        $site = $this->createStub(Site::class);

        $dto = new DatabaseIndexingDto(
            title: 'Original Title',
            content: 'Original Content',
            pageUid: 1,
            languageUid: 0,
            arguments: [],
            site: $site,
        );

        $dto->title = 'Modified Title';
        $dto->content = 'Modified Content';

        self::assertSame('Modified Title', $dto->title);
        self::assertSame('Modified Content', $dto->content);
    }

    public function testArgumentsAreMutable(): void
    {
        $site = $this->createStub(Site::class);

        $dto = new DatabaseIndexingDto(
            title: 'Title',
            content: 'Content',
            pageUid: 1,
            languageUid: 0,
            arguments: [],
            site: $site,
        );

        $dto->arguments['new_key'] = 'new_value';

        self::assertSame(['new_key' => 'new_value'], $dto->arguments);
    }

    public function testPageUidAndLanguageUidAreReadonly(): void
    {
        $site = $this->createStub(Site::class);

        $dto = new DatabaseIndexingDto(
            title: 'Title',
            content: 'Content',
            pageUid: 42,
            languageUid: 1,
            arguments: [],
            site: $site,
        );

        self::assertSame(42, $dto->pageUid);
        self::assertSame(1, $dto->languageUid);
    }
}
