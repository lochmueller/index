<?php

declare(strict_types=1);

namespace Lochmueller\Index\Tests\Unit\Traversing;

use Lochmueller\Index\Tests\Unit\AbstractTest;
use Lochmueller\Index\Traversing\FrontendInformationDto;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Http\Message\UriInterface;
use TYPO3\CMS\Core\Site\Entity\SiteLanguage;

class FrontendInformationDtoTest extends AbstractTest
{
    private UriInterface&MockObject $uriMock;

    private SiteLanguage&MockObject $languageMock;

    protected function setUp(): void
    {
        parent::setUp();
        $this->uriMock = $this->createMock(UriInterface::class);
        $this->languageMock = $this->createMock(SiteLanguage::class);
    }

    /**
     * Tests: Requirements 1.1
     */
    public function testConstructorWithAccessGroupsParameter(): void
    {
        $accessGroups = [1, 2, 3];

        $dto = new FrontendInformationDto(
            uri: $this->uriMock,
            arguments: ['foo' => 'bar'],
            pageUid: 42,
            language: $this->languageMock,
            row: ['uid' => 42],
            accessGroups: $accessGroups,
        );

        self::assertSame($accessGroups, $dto->accessGroups);
    }

    /**
     * Tests: Requirements 1.1
     */
    public function testConstructorWithoutAccessGroupsParameterUsesEmptyArrayDefault(): void
    {
        $dto = new FrontendInformationDto(
            uri: $this->uriMock,
            arguments: ['foo' => 'bar'],
            pageUid: 42,
            language: $this->languageMock,
            row: ['uid' => 42],
        );

        self::assertSame([], $dto->accessGroups);
    }

    /**
     * Tests: Requirements 1.1
     */
    public function testConstructorWithSpecialAccessGroupValues(): void
    {
        $accessGroups = [-1, -2, 5];

        $dto = new FrontendInformationDto(
            uri: $this->uriMock,
            arguments: [],
            pageUid: 1,
            language: $this->languageMock,
            row: [],
            accessGroups: $accessGroups,
        );

        self::assertSame($accessGroups, $dto->accessGroups);
    }

    public function testAllPropertiesAreAccessible(): void
    {
        $arguments = ['type' => 'page'];
        $row = ['uid' => 42, 'title' => 'Test Page'];
        $accessGroups = [1, 2];

        $dto = new FrontendInformationDto(
            uri: $this->uriMock,
            arguments: $arguments,
            pageUid: 42,
            language: $this->languageMock,
            row: $row,
            accessGroups: $accessGroups,
        );

        self::assertSame($this->uriMock, $dto->uri);
        self::assertSame($arguments, $dto->arguments);
        self::assertSame(42, $dto->pageUid);
        self::assertSame($this->languageMock, $dto->language);
        self::assertSame($row, $dto->row);
        self::assertSame($accessGroups, $dto->accessGroups);
    }
}
