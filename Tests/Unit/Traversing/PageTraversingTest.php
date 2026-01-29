<?php

declare(strict_types=1);

namespace Lochmueller\Index\Tests\Unit\Traversing;

use Lochmueller\Index\Configuration\Configuration;
use Lochmueller\Index\Configuration\ConfigurationLoader;
use Lochmueller\Index\Domain\Repository\PagesRepository;
use Lochmueller\Index\Tests\Unit\AbstractTest;
use Lochmueller\Index\Traversing\PageTraversing;
use Lochmueller\Index\Traversing\RecordSelection;
use PHPUnit\Framework\Attributes\DataProvider;
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
        $siteStub = $this->createStub(Site::class);
        $siteStub->method('getLanguages')->willReturn([]);
        $siteStub->method('getRouter')->willReturn($this->createStub(\TYPO3\CMS\Core\Routing\PageRouter::class));

        $siteFinderStub = $this->createStub(SiteFinder::class);
        $siteFinderStub->method('getSiteByPageId')->willReturn($siteStub);

        $configurationLoaderStub = $this->createStub(ConfigurationLoader::class);
        $configurationLoaderStub->method('loadByPage')->willReturn(null);

        $subject = new PageTraversing(
            $siteFinderStub,
            [],
            $configurationLoaderStub,
            $this->createStub(RecordSelection::class),
            $this->createStub(PagesRepository::class),
        );

        $configuration = $this->createConfigurationStub(1, 1, 0);

        $result = iterator_to_array($subject->getFrontendInformation($configuration));

        self::assertSame([], $result);
    }

    #[DataProvider('languageFilterDataProvider')]
    public function testGetFrontendInformationFiltersLanguagesCorrectly(array $configuredLanguages, array $siteLanguageIds, array $expectedLanguageIds): void
    {
        $siteLanguages = [];
        foreach ($siteLanguageIds as $id) {
            $langStub = $this->createStub(SiteLanguage::class);
            $langStub->method('getLanguageId')->willReturn($id);
            $siteLanguages[$id] = $langStub;
        }

        $routerStub = $this->createStub(\TYPO3\CMS\Core\Routing\PageRouter::class);

        $siteStub = $this->createStub(Site::class);
        $siteStub->method('getLanguages')->willReturn($siteLanguages);
        $siteStub->method('getRouter')->willReturn($routerStub);

        $siteFinderStub = $this->createStub(SiteFinder::class);
        $siteFinderStub->method('getSiteByPageId')->willReturn($siteStub);

        $configurationLoaderStub = $this->createStub(ConfigurationLoader::class);
        $configurationLoaderStub->method('loadByPage')->willReturn(null);

        $recordSelectionStub = $this->createStub(RecordSelection::class);
        $recordSelectionStub->method('findRenderablePage')->willReturn(null);

        $subject = new PageTraversing(
            $siteFinderStub,
            [],
            $configurationLoaderStub,
            $recordSelectionStub,
            $this->createStub(PagesRepository::class),
        );

        $configuration = $this->createConfigurationStub(1, 1, 0, $configuredLanguages);

        $result = iterator_to_array($subject->getFrontendInformation($configuration));

        self::assertSame([], $result);
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

    private function createConfigurationStub(int $configurationId, int $pageId, int $levels, array $languages = []): Configuration
    {
        return new Configuration(
            configurationId: $configurationId,
            pageId: $pageId,
            technology: \Lochmueller\Index\Enums\IndexTechnology::Frontend,
            skipNoSearchPages: false,
            contentIndexing: false,
            levels: $levels,
            fileMounts: [],
            fileTypes: [],
            configuration: [],
            partialIndexing: [],
            languages: $languages,
        );
    }
}
