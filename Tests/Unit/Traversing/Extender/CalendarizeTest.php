<?php

declare(strict_types=1);

namespace Lochmueller\Index\Tests\Unit\Traversing\Extender;

use Lochmueller\Index\Configuration\Configuration;
use Lochmueller\Index\Enums\IndexTechnology;
use Lochmueller\Index\Tests\Unit\AbstractTest;
use Lochmueller\Index\Traversing\Extender\Calendarize;
use Lochmueller\Index\Traversing\Extender\ExtenderInterface;
use Lochmueller\Index\Traversing\FrontendInformationDto;
use Lochmueller\Index\Traversing\RecordSelection;
use Psr\Http\Message\UriInterface;
use TYPO3\CMS\Core\Domain\Record;
use TYPO3\CMS\Core\Routing\PageRouter;
use TYPO3\CMS\Core\Site\Entity\Site;
use TYPO3\CMS\Core\Site\Entity\SiteLanguage;

class CalendarizeTest extends AbstractTest
{
    public function testImplementsExtenderInterface(): void
    {
        $subject = new Calendarize($this->createStub(RecordSelection::class));

        self::assertInstanceOf(ExtenderInterface::class, $subject);
    }

    public function testGetNameReturnsCalendarize(): void
    {
        $subject = new Calendarize($this->createStub(RecordSelection::class));

        self::assertSame('calendarize', $subject->getName());
    }

    public function testGetItemsReturnsEmptyIterableWhenNoRecordsFound(): void
    {
        $recordSelectionStub = $this->createStub(RecordSelection::class);
        $recordSelectionStub->method('findRecordsOnPage')->willReturn([]);

        $subject = new Calendarize($recordSelectionStub);

        $result = iterator_to_array($subject->getItems(
            $this->createConfigurationStub(),
            ['recordStorages' => [10]],
            $this->createSiteStub(),
            1,
            $this->createSiteLanguageStub(0),
            [],
        ));

        self::assertSame([], $result);
    }

    public function testGetItemsYieldsFrontendInformationDtoForRecordTypeZero(): void
    {
        $recordStub = $this->createStub(Record::class);
        $recordStub->method('getUid')->willReturn(42);
        $recordStub->method('getRecordType')->willReturn('0');

        $recordSelectionStub = $this->createStub(RecordSelection::class);
        $recordSelectionStub->method('findRecordsOnPage')->willReturn([$recordStub]);

        $uriStub = $this->createStub(UriInterface::class);

        $routerStub = $this->createStub(PageRouter::class);
        $routerStub->method('generateUri')->willReturn($uriStub);

        $siteStub = $this->createStub(Site::class);
        $siteStub->method('getRouter')->willReturn($routerStub);

        $languageStub = $this->createSiteLanguageStub(1);

        $subject = new Calendarize($recordSelectionStub);

        $result = iterator_to_array($subject->getItems(
            $this->createConfigurationStub(),
            ['recordStorages' => [10]],
            $siteStub,
            5,
            $languageStub,
            ['uid' => 5],
        ));

        self::assertCount(1, $result);
        self::assertInstanceOf(FrontendInformationDto::class, $result[0]);
        self::assertSame(5, $result[0]->pageUid);
        self::assertSame($languageStub, $result[0]->language);
    }

    public function testGetItemsSkipsRecordsWithNonZeroRecordType(): void
    {
        $recordStub = $this->createStub(Record::class);
        $recordStub->method('getUid')->willReturn(42);
        $recordStub->method('getRecordType')->willReturn('1');

        $recordSelectionStub = $this->createStub(RecordSelection::class);
        $recordSelectionStub->method('findRecordsOnPage')->willReturn([$recordStub]);

        $subject = new Calendarize($recordSelectionStub);

        $result = iterator_to_array($subject->getItems(
            $this->createConfigurationStub(),
            ['recordStorages' => [10]],
            $this->createSiteStub(),
            1,
            $this->createSiteLanguageStub(0),
            [],
        ));

        self::assertSame([], $result);
    }

    public function testGetItemsUsesCorrectArgumentsForCalendarizePlugin(): void
    {
        $recordStub = $this->createStub(Record::class);
        $recordStub->method('getUid')->willReturn(99);
        $recordStub->method('getRecordType')->willReturn('0');

        $recordSelectionStub = $this->createStub(RecordSelection::class);
        $recordSelectionStub->method('findRecordsOnPage')->willReturn([$recordStub]);

        $languageStub = $this->createSiteLanguageStub(2);

        $routerMock = $this->createMock(PageRouter::class);
        $routerMock->expects(self::once())
            ->method('generateUri')
            ->with(
                10,
                self::callback(fn(array $arguments): bool => $arguments['_language'] === $languageStub
                        && $arguments['tx_calendarize_calendar']['action'] === 'detail'
                        && $arguments['tx_calendarize_calendar']['controller'] === 'Calendar'
                        && $arguments['tx_calendarize_calendar']['index'] === 99),
            )
            ->willReturn($this->createStub(UriInterface::class));

        $siteStub = $this->createStub(Site::class);
        $siteStub->method('getRouter')->willReturn($routerMock);

        $subject = new Calendarize($recordSelectionStub);

        iterator_to_array($subject->getItems(
            $this->createConfigurationStub(),
            ['recordStorages' => [5]],
            $siteStub,
            10,
            $languageStub,
            [],
        ));
    }

    public function testGetItemsUsesCorrectTableName(): void
    {
        $recordSelectionMock = $this->createMock(RecordSelection::class);
        $recordSelectionMock->expects(self::once())
            ->method('findRecordsOnPage')
            ->with('tx_calendarize_domain_model_index', [15], 0)
            ->willReturn([]);

        $subject = new Calendarize($recordSelectionMock);

        iterator_to_array($subject->getItems(
            $this->createConfigurationStub(),
            ['recordStorages' => [15]],
            $this->createSiteStub(),
            1,
            $this->createSiteLanguageStub(0),
            [],
        ));
    }

    private function createConfigurationStub(): Configuration
    {
        return new Configuration(
            configurationId: 1,
            pageId: 1,
            technology: IndexTechnology::Frontend,
            skipNoSearchPages: false,
            contentIndexing: false,
            levels: 0,
            fileMounts: [],
            fileTypes: [],
            configuration: [],
            partialIndexing: [],
            languages: [],
        );
    }

    private function createSiteStub(): Site
    {
        $routerStub = $this->createStub(PageRouter::class);
        $routerStub->method('generateUri')->willReturn($this->createStub(UriInterface::class));

        $siteStub = $this->createStub(Site::class);
        $siteStub->method('getRouter')->willReturn($routerStub);

        return $siteStub;
    }

    private function createSiteLanguageStub(int $languageId): SiteLanguage
    {
        $stub = $this->createStub(SiteLanguage::class);
        $stub->method('getLanguageId')->willReturn($languageId);

        return $stub;
    }
}
