<?php

declare(strict_types=1);

namespace Lochmueller\Index\Tests\Unit\Traversing;

use Lochmueller\Index\Configuration\Configuration;
use Lochmueller\Index\Configuration\ConfigurationLoader;
use Lochmueller\Index\Domain\Repository\PagesRepository;
use Lochmueller\Index\Enums\IndexTechnology;
use Lochmueller\Index\Tests\Unit\AbstractTest;
use Lochmueller\Index\Traversing\Extender\ExtenderInterface;
use Lochmueller\Index\Traversing\FrontendInformationDto;
use Lochmueller\Index\Traversing\PageTraversing;
use Lochmueller\Index\Traversing\RecordSelection;
use PHPUnit\Framework\Attributes\DataProvider;
use TYPO3\CMS\Core\Http\Uri;
use TYPO3\CMS\Core\Routing\PageRouter;
use TYPO3\CMS\Core\Site\Entity\Site;
use TYPO3\CMS\Core\Site\Entity\SiteLanguage;
use TYPO3\CMS\Core\Site\SiteFinder;

class PageTraversingTest extends AbstractTest
{
    public function testClassCanBeInstantiated(): void
    {
        $subject = new PageTraversing(
            $this->createStub(SiteFinder::class),
            [],
            $this->createStub(ConfigurationLoader::class),
            $this->createStub(RecordSelection::class),
            $this->createStub(PagesRepository::class),
        );

        self::assertInstanceOf(PageTraversing::class, $subject);
    }

    public function testGetFrontendInformationReturnsEmptyIterableWhenNoLanguagesConfigured(): void
    {
        $subject = $this->createSubject(siteLanguageIds: []);

        $result = iterator_to_array($subject->getFrontendInformation($this->createConfiguration()));

        self::assertSame([], $result);
    }

    public function testGetFrontendInformationSkipsPagesThatAreNotRenderable(): void
    {
        $recordSelectionStub = $this->createStub(RecordSelection::class);
        $recordSelectionStub->method('findRenderablePage')->willReturn(null);

        $subject = $this->createSubject(recordSelection: $recordSelectionStub);

        $result = iterator_to_array($subject->getFrontendInformation($this->createConfiguration()));

        self::assertSame([], $result);
    }

    /**
     * @param array<int> $configuredLanguages
     * @param array<int> $siteLanguageIds
     * @param array<int> $expectedLanguageIds
     */
    #[DataProvider('languageFilterDataProvider')]
    public function testGetFrontendInformationFiltersLanguagesCorrectly(array $configuredLanguages, array $siteLanguageIds, array $expectedLanguageIds): void
    {
        $subject = $this->createSubject(siteLanguageIds: $siteLanguageIds);

        $result = $this->collect($subject->getFrontendInformation($this->createConfiguration(languages: $configuredLanguages)));

        self::assertSame(
            $expectedLanguageIds,
            array_map(static fn(FrontendInformationDto $dto): int => $dto->language->getLanguageId(), $result),
        );
    }

    /**
     * @return iterable<string, array{array<int>, array<int>, array<int>}>
     */
    public static function languageFilterDataProvider(): iterable
    {
        yield 'empty config uses all site languages' => [[], [0, 1, 2], [0, 1, 2]];
        yield 'specific languages filter site languages' => [[0, 2], [0, 1, 2], [0, 2]];
        yield 'non-existing language is ignored' => [[0, 99], [0, 1], [0]];
    }

    public function testGetFrontendInformationCreatesDtoWithExpectedValues(): void
    {
        $row = ['uid' => 1, 'fe_group' => '1,2', 'no_search' => 0];
        $recordSelectionStub = $this->createStub(RecordSelection::class);
        $recordSelectionStub->method('findRenderablePage')->willReturn($row);

        $subject = $this->createSubject(recordSelection: $recordSelectionStub);

        $result = $this->collect($subject->getFrontendInformation($this->createConfiguration()));

        self::assertCount(1, $result);
        $dto = $result[0];
        self::assertSame('https://example.com/page-1', (string) $dto->uri);
        self::assertSame(1, $dto->pageUid);
        self::assertSame(0, $dto->language->getLanguageId());
        self::assertSame(['_language' => $dto->language], $dto->arguments);
        self::assertSame($row, $dto->row);
        self::assertSame([1, 2], $dto->accessGroups);
    }

    public function testGetFrontendInformationUsesEmptyAccessGroupsWithoutFeGroup(): void
    {
        $recordSelectionStub = $this->createStub(RecordSelection::class);
        $recordSelectionStub->method('findRenderablePage')->willReturn(['uid' => 1]);

        $subject = $this->createSubject(recordSelection: $recordSelectionStub);

        $result = $this->collect($subject->getFrontendInformation($this->createConfiguration()));

        self::assertCount(1, $result);
        self::assertSame([], $result[0]->accessGroups);
    }

    /**
     * @param array<string, mixed> $row
     */
    #[DataProvider('noSearchDataProvider')]
    public function testGetFrontendInformationRespectsSkipNoSearchPages(bool $skipNoSearchPages, array $row, int $expectedCount): void
    {
        $recordSelectionStub = $this->createStub(RecordSelection::class);
        $recordSelectionStub->method('findRenderablePage')->willReturn($row);

        $subject = $this->createSubject(recordSelection: $recordSelectionStub);

        $result = $this->collect($subject->getFrontendInformation($this->createConfiguration(skipNoSearchPages: $skipNoSearchPages)));

        self::assertCount($expectedCount, $result);
    }

    /**
     * @return iterable<string, array{bool, array<string, mixed>, int}>
     */
    public static function noSearchDataProvider(): iterable
    {
        yield 'skip enabled and no_search set' => [true, ['uid' => 1, 'no_search' => 1], 0];
        yield 'skip enabled and no_search not set' => [true, ['uid' => 1, 'no_search' => 0], 1];
        yield 'skip enabled and no_search missing' => [true, ['uid' => 1], 1];
        yield 'skip disabled and no_search set' => [false, ['uid' => 1, 'no_search' => 1], 1];
    }

    public function testGetFrontendInformationTraversesChildPagesRespectingLevels(): void
    {
        $pagesRepositoryStub = $this->createStub(PagesRepository::class);
        $pagesRepositoryStub->method('findChildPageUids')->willReturnMap([
            [1, [2, 3]],
            [2, [4]],
            [3, []],
            [4, [5]],
            [5, []],
        ]);

        $subject = $this->createSubject(pagesRepository: $pagesRepositoryStub);

        $result = $this->collect($subject->getFrontendInformation($this->createConfiguration(levels: 2)));

        self::assertSame([1, 2, 4, 3], $this->pageUids($result));
    }

    public function testGetFrontendInformationDoesNotTraverseChildrenWithZeroLevels(): void
    {
        $pagesRepositoryMock = $this->createMock(PagesRepository::class);
        $pagesRepositoryMock->expects(self::never())->method('findChildPageUids');

        $subject = $this->createSubject(pagesRepository: $pagesRepositoryMock);

        $result = $this->collect($subject->getFrontendInformation($this->createConfiguration(levels: 0)));

        self::assertSame([1], $this->pageUids($result));
    }

    public function testGetFrontendInformationDoesNotTraverseChildrenOfRootPageZero(): void
    {
        $pagesRepositoryMock = $this->createMock(PagesRepository::class);
        $pagesRepositoryMock->expects(self::never())->method('findChildPageUids');

        $subject = $this->createSubject(pagesRepository: $pagesRepositoryMock);

        $result = $this->collect($subject->getFrontendInformation($this->createConfiguration(pageId: 0, levels: 5)));

        self::assertSame([0], $this->pageUids($result));
    }

    public function testGetFrontendInformationStopsTraversingOnForeignConfiguration(): void
    {
        $pagesRepositoryStub = $this->createStub(PagesRepository::class);
        $pagesRepositoryStub->method('findChildPageUids')->willReturnMap([
            [1, [2, 3]],
            [2, [4]],
            [3, []],
            [4, []],
        ]);

        $configurationLoaderStub = $this->createStub(ConfigurationLoader::class);
        $configurationLoaderStub->method('loadByPage')->willReturnCallback(
            fn(int $pageId): ?Configuration => match ($pageId) {
                1 => $this->createConfiguration(configurationId: 1),
                2 => $this->createConfiguration(configurationId: 2, pageId: 2),
                default => null,
            },
        );

        $subject = $this->createSubject(configurationLoader: $configurationLoaderStub, pagesRepository: $pagesRepositoryStub);

        $result = $this->collect($subject->getFrontendInformation($this->createConfiguration(levels: 5)));

        self::assertSame([1, 3], $this->pageUids($result));
    }

    public function testGetFrontendInformationYieldsExtenderItemsAndOriginalUri(): void
    {
        $extenderItem = $this->createDto(100);
        $subject = $this->createSubject(extender: [
            $this->createExtender('other', [$this->createDto(200)]),
            $this->createExtender('news', [$extenderItem]),
        ]);

        $result = $this->collect($subject->getFrontendInformation($this->createConfiguration(configuration: [
            'extender' => [
                ['type' => 'news'],
            ],
        ])));

        self::assertCount(2, $result);
        self::assertSame($extenderItem, $result[0]);
        self::assertSame('https://example.com/page-1', (string) $result[1]->uri);
    }

    public function testGetFrontendInformationDropsOriginalUriIfConfigured(): void
    {
        $extenderItem = $this->createDto(100);
        $subject = $this->createSubject(extender: [$this->createExtender('news', [$extenderItem])]);

        $result = $this->collect($subject->getFrontendInformation($this->createConfiguration(configuration: [
            'extender' => [
                ['type' => 'news', 'dropOriginalUri' => true],
            ],
        ])));

        self::assertSame([$extenderItem], $result);
    }

    public function testGetFrontendInformationDropOriginalUriSkipsFollowingExtenderConfigurations(): void
    {
        $newsItem = $this->createDto(100);
        $subject = $this->createSubject(extender: [
            $this->createExtender('news', [$newsItem]),
            $this->createExtender('events', [$this->createDto(200)]),
        ]);

        $result = $this->collect($subject->getFrontendInformation($this->createConfiguration(configuration: [
            'extender' => [
                ['type' => 'news', 'dropOriginalUri' => true],
                ['type' => 'events'],
            ],
        ])));

        self::assertSame([$newsItem], $result);
    }

    public function testGetFrontendInformationProcessesMultipleExtenderConfigurations(): void
    {
        $newsItem = $this->createDto(100);
        $eventItem = $this->createDto(200);
        $subject = $this->createSubject(extender: [
            $this->createExtender('news', [$newsItem]),
            $this->createExtender('events', [$eventItem]),
        ]);

        $result = $this->collect($subject->getFrontendInformation($this->createConfiguration(configuration: [
            'extender' => [
                ['type' => 'news'],
                ['type' => 'events'],
            ],
        ])));

        self::assertCount(3, $result);
        self::assertSame($newsItem, $result[0]);
        self::assertSame($eventItem, $result[1]);
        self::assertSame(1, $result[2]->pageUid);
    }

    public function testGetFrontendInformationIgnoresExtenderOutsideOfLimitToPages(): void
    {
        $pagesRepositoryStub = $this->createStub(PagesRepository::class);
        $pagesRepositoryStub->method('findChildPageUids')->willReturnMap([
            [1, [2]],
            [2, []],
        ]);

        $extenderItem = $this->createDto(100);
        $subject = $this->createSubject(
            extender: [$this->createExtender('news', [$extenderItem])],
            pagesRepository: $pagesRepositoryStub,
        );

        $result = $this->collect($subject->getFrontendInformation($this->createConfiguration(levels: 1, configuration: [
            'extender' => [
                ['type' => 'news', 'limitToPages' => [2], 'dropOriginalUri' => true],
            ],
        ])));

        self::assertCount(2, $result);
        self::assertSame(1, $result[0]->pageUid);
        self::assertSame($extenderItem, $result[1]);
    }

    public function testGetFrontendInformationIgnoresExtenderWithInvalidLimitToPages(): void
    {
        $subject = $this->createSubject(extender: [$this->createExtender('news', [$this->createDto(100)])]);

        $result = $this->collect($subject->getFrontendInformation($this->createConfiguration(configuration: [
            'extender' => [
                ['type' => 'news', 'limitToPages' => '1', 'dropOriginalUri' => true],
            ],
        ])));

        self::assertSame([1], $this->pageUids($result));
    }

    public function testGetFrontendInformationIgnoresUnknownExtenderType(): void
    {
        $subject = $this->createSubject(extender: [$this->createExtender('news', [$this->createDto(100)])]);

        $result = $this->collect($subject->getFrontendInformation($this->createConfiguration(configuration: [
            'extender' => [
                ['type' => 'unknown', 'dropOriginalUri' => true],
                ['dropOriginalUri' => true],
            ],
        ])));

        self::assertSame([1], $this->pageUids($result));
    }

    public function testGetFrontendInformationDropOriginalUriBypassesNoSearchCheck(): void
    {
        $recordSelectionStub = $this->createStub(RecordSelection::class);
        $recordSelectionStub->method('findRenderablePage')->willReturn(['uid' => 1, 'no_search' => 1]);

        $extenderItem = $this->createDto(100);
        $subject = $this->createSubject(
            extender: [$this->createExtender('news', [$extenderItem])],
            recordSelection: $recordSelectionStub,
        );

        $result = $this->collect($subject->getFrontendInformation($this->createConfiguration(skipNoSearchPages: true, configuration: [
            'extender' => [
                ['type' => 'news'],
            ],
        ])));

        self::assertSame([$extenderItem], $result);
    }

    /**
     * @param iterable<ExtenderInterface> $extender
     * @param array<int> $siteLanguageIds
     */
    private function createSubject(
        iterable $extender = [],
        array $siteLanguageIds = [0],
        ?ConfigurationLoader $configurationLoader = null,
        ?RecordSelection $recordSelection = null,
        ?PagesRepository $pagesRepository = null,
    ): PageTraversing {
        $siteLanguages = [];
        foreach ($siteLanguageIds as $languageId) {
            $siteLanguages[$languageId] = $this->createSiteLanguage($languageId);
        }

        $routerStub = $this->createStub(PageRouter::class);
        $routerStub->method('generateUri')->willReturnCallback(
            static fn(int $pageUid): Uri => new Uri('https://example.com/page-' . $pageUid),
        );

        $siteStub = $this->createStub(Site::class);
        $siteStub->method('getLanguages')->willReturn($siteLanguages);
        $siteStub->method('getRouter')->willReturn($routerStub);

        $siteFinderStub = $this->createStub(SiteFinder::class);
        $siteFinderStub->method('getSiteByPageId')->willReturn($siteStub);

        if ($configurationLoader === null) {
            $configurationLoader = $this->createStub(ConfigurationLoader::class);
            $configurationLoader->method('loadByPage')->willReturn(null);
        }

        if ($recordSelection === null) {
            $recordSelection = $this->createStub(RecordSelection::class);
            $recordSelection->method('findRenderablePage')->willReturnCallback(
                static fn(int $pageUid, int $language = 0): array => ['uid' => $pageUid, 'sys_language_uid' => $language],
            );
        }

        if ($pagesRepository === null) {
            $pagesRepository = $this->createStub(PagesRepository::class);
            $pagesRepository->method('findChildPageUids')->willReturn([]);
        }

        return new PageTraversing(
            $siteFinderStub,
            $extender,
            $configurationLoader,
            $recordSelection,
            $pagesRepository,
        );
    }

    private function createSiteLanguage(int $languageId): SiteLanguage
    {
        $languageStub = $this->createStub(SiteLanguage::class);
        $languageStub->method('getLanguageId')->willReturn($languageId);

        return $languageStub;
    }

    /**
     * @param array<FrontendInformationDto> $items
     */
    private function createExtender(string $name, array $items): ExtenderInterface
    {
        $extenderStub = $this->createStub(ExtenderInterface::class);
        $extenderStub->method('getName')->willReturn($name);
        $extenderStub->method('getItems')->willReturn($items);

        return $extenderStub;
    }

    private function createDto(int $pageUid): FrontendInformationDto
    {
        return new FrontendInformationDto(
            uri: new Uri('https://example.com/extended-' . $pageUid),
            arguments: [],
            pageUid: $pageUid,
            language: $this->createSiteLanguage(0),
            row: [],
        );
    }

    /**
     * @param iterable<FrontendInformationDto> $items
     * @return list<FrontendInformationDto>
     */
    private function collect(iterable $items): array
    {
        return iterator_to_array($items, false);
    }

    /**
     * @param list<FrontendInformationDto> $items
     * @return list<int>
     */
    private function pageUids(array $items): array
    {
        return array_map(static fn(FrontendInformationDto $dto): int => $dto->pageUid, $items);
    }

    /**
     * @param array<int> $languages
     * @param array<string, mixed> $configuration
     */
    private function createConfiguration(
        int $configurationId = 1,
        int $pageId = 1,
        int $levels = 0,
        array $languages = [],
        bool $skipNoSearchPages = false,
        array $configuration = [],
    ): Configuration {
        return new Configuration(
            configurationId: $configurationId,
            pageId: $pageId,
            technology: IndexTechnology::Frontend,
            skipNoSearchPages: $skipNoSearchPages,
            contentIndexing: false,
            levels: $levels,
            fileMounts: [],
            fileTypes: [],
            configuration: $configuration,
            partialIndexing: [],
            languages: $languages,
        );
    }
}
