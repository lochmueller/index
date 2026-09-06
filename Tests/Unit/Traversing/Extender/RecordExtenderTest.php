<?php

declare(strict_types=1);

namespace Lochmueller\Index\Tests\Unit\Traversing\Extender;

use Lochmueller\Index\Configuration\Configuration;
use Lochmueller\Index\Enums\IndexTechnology;
use Lochmueller\Index\Tests\Unit\AbstractTest;
use Lochmueller\Index\Traversing\Extender\ExtenderInterface;
use Lochmueller\Index\Traversing\Extender\RecordExtender;
use Lochmueller\Index\Traversing\FrontendInformationDto;
use Lochmueller\Index\Traversing\RecordSelection;
use PHPUnit\Framework\MockObject\Stub;
use Psr\Http\Message\UriInterface;
use TYPO3\CMS\Core\Domain\Record;
use TYPO3\CMS\Core\Routing\PageRouter;
use TYPO3\CMS\Core\Site\Entity\Site;
use TYPO3\CMS\Core\Site\Entity\SiteLanguage;

class RecordExtenderTest extends AbstractTest
{
    public function testImplementsExtenderInterface(): void
    {
        $subject = new RecordExtender($this->createStub(RecordSelection::class));

        self::assertInstanceOf(ExtenderInterface::class, $subject);
    }

    public function testGetNameReturnsRecord(): void
    {
        $subject = new RecordExtender($this->createStub(RecordSelection::class));

        self::assertSame('record', $subject->getName());
    }

    public function testGetItemsReturnsNothingWithoutTable(): void
    {
        $recordSelectionMock = $this->createMock(RecordSelection::class);
        $recordSelectionMock->expects(self::never())->method('findRecordsOnPage');

        $subject = new RecordExtender($recordSelectionMock);

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

    public function testGetItemsUsesTableAndStoragesOfTheConfiguration(): void
    {
        $recordSelectionMock = $this->createMock(RecordSelection::class);
        $recordSelectionMock->expects(self::once())
            ->method('findRecordsOnPage')
            ->with('tx_my_domain_model_item', [12, 24], 2)
            ->willReturn([]);

        $subject = new RecordExtender($recordSelectionMock);

        iterator_to_array($subject->getItems(
            $this->createConfigurationStub(),
            ['table' => 'tx_my_domain_model_item', 'recordStorages' => [12, '24']],
            $this->createSiteStub(),
            1,
            $this->createSiteLanguageStub(2),
            [],
        ));
    }

    public function testGetItemsFallsBackToTheCurrentPageAsStorage(): void
    {
        $recordSelectionMock = $this->createMock(RecordSelection::class);
        $recordSelectionMock->expects(self::once())
            ->method('findRecordsOnPage')
            ->with('tx_my_domain_model_item', [42], 0)
            ->willReturn([]);

        $subject = new RecordExtender($recordSelectionMock);

        iterator_to_array($subject->getItems(
            $this->createConfigurationStub(),
            ['table' => 'tx_my_domain_model_item'],
            $this->createSiteStub(),
            42,
            $this->createSiteLanguageStub(0),
            [],
        ));
    }

    public function testGetItemsYieldsFrontendInformationDtoPerRecord(): void
    {
        $subject = new RecordExtender($this->createRecordSelectionStub([
            $this->createRecordStub(1),
            $this->createRecordStub(2),
        ]));

        $languageStub = $this->createSiteLanguageStub(1);

        $result = iterator_to_array($subject->getItems(
            $this->createConfigurationStub(),
            ['table' => 'tx_my_domain_model_item'],
            $this->createSiteStub(),
            5,
            $languageStub,
            ['uid' => 5],
        ));

        self::assertCount(2, $result);
        self::assertInstanceOf(FrontendInformationDto::class, $result[0]);
        self::assertSame(5, $result[0]->pageUid);
        self::assertSame($languageStub, $result[0]->language);
        self::assertSame(['uid' => 5], $result[0]->row);
    }

    public function testGetItemsFiltersByRecordTypes(): void
    {
        $subject = new RecordExtender($this->createRecordSelectionStub([
            $this->createRecordStub(1, '0'),
            $this->createRecordStub(2, '1'),
        ]));

        $result = iterator_to_array($subject->getItems(
            $this->createConfigurationStub(),
            ['table' => 'tx_my_domain_model_item', 'recordTypes' => ['0']],
            $this->createSiteStub(),
            5,
            $this->createSiteLanguageStub(0),
            [],
        ));

        self::assertCount(1, $result);
    }

    public function testGetItemsFiltersByConstraints(): void
    {
        $subject = new RecordExtender($this->createRecordSelectionStub([
            $this->createRecordStub(1, null, ['type' => 'event']),
            $this->createRecordStub(2, null, ['type' => 'news']),
            $this->createRecordStub(3, null, ['other' => 'event']),
        ]));

        $result = iterator_to_array($subject->getItems(
            $this->createConfigurationStub(),
            ['table' => 'tx_my_domain_model_item', 'constraints' => ['type' => ['event', 'blog']]],
            $this->createSiteStub(),
            5,
            $this->createSiteLanguageStub(0),
            [],
        ));

        self::assertCount(1, $result);
    }

    public function testGetItemsResolvesPlaceholdersInArguments(): void
    {
        $languageStub = $this->createSiteLanguageStub(3);

        $routerMock = $this->createMock(PageRouter::class);
        $routerMock->expects(self::once())
            ->method('generateUri')
            ->with(
                10,
                self::callback(fn(array $arguments): bool => $arguments['_language'] === $languageStub
                    && $arguments['tx_my_plugin']['controller'] === 'Item'
                    && $arguments['tx_my_plugin']['action'] === 'detail'
                    && $arguments['tx_my_plugin']['item'] === 99
                    && $arguments['tx_my_plugin']['slug'] === 'my-slug'
                    && $arguments['tx_my_plugin']['combined'] === '99-my-slug'
                    && $arguments['tx_my_plugin']['language'] === 3
                    && $arguments['tx_my_plugin']['page'] === 10
                    && $arguments['tx_my_plugin']['unknown'] === '{whatever}'),
            )
            ->willReturn($this->createStub(UriInterface::class));

        $siteStub = $this->createStub(Site::class);
        $siteStub->method('getRouter')->willReturn($routerMock);

        $subject = new RecordExtender($this->createRecordSelectionStub([
            $this->createRecordStub(99, null, ['slug' => 'my-slug']),
        ]));

        iterator_to_array($subject->getItems(
            $this->createConfigurationStub(),
            [
                'table' => 'tx_my_domain_model_item',
                'arguments' => [
                    'tx_my_plugin' => [
                        'controller' => 'Item',
                        'action' => 'detail',
                        'item' => '{uid}',
                        'slug' => '{field:slug}',
                        'combined' => '{uid}-{field:slug}',
                        'language' => '{languageId}',
                        'page' => '{pageUid}',
                        'unknown' => '{whatever}',
                    ],
                ],
            ],
            $siteStub,
            10,
            $languageStub,
            [],
        ));
    }

    public function testGetItemsDoesNotAllowToOverrideTheLanguageArgument(): void
    {
        $languageStub = $this->createSiteLanguageStub(1);

        $routerMock = $this->createMock(PageRouter::class);
        $routerMock->expects(self::once())
            ->method('generateUri')
            ->with(10, self::callback(fn(array $arguments): bool => $arguments['_language'] === $languageStub))
            ->willReturn($this->createStub(UriInterface::class));

        $siteStub = $this->createStub(Site::class);
        $siteStub->method('getRouter')->willReturn($routerMock);

        $subject = new RecordExtender($this->createRecordSelectionStub([$this->createRecordStub(1)]));

        iterator_to_array($subject->getItems(
            $this->createConfigurationStub(),
            [
                'table' => 'tx_my_domain_model_item',
                'arguments' => ['_language' => 5],
            ],
            $siteStub,
            10,
            $languageStub,
            [],
        ));
    }

    /**
     * @param Record[] $records
     */
    private function createRecordSelectionStub(array $records): RecordSelection&Stub
    {
        $stub = $this->createStub(RecordSelection::class);
        $stub->method('findRecordsOnPage')->willReturn($records);

        return $stub;
    }

    /**
     * @param array<string, mixed> $properties
     */
    private function createRecordStub(int $uid, ?string $recordType = null, array $properties = []): Record&Stub
    {
        $stub = $this->createStub(Record::class);
        $stub->method('getUid')->willReturn($uid);
        $stub->method('getPid')->willReturn(1);
        $stub->method('getRecordType')->willReturn($recordType);
        $stub->method('has')->willReturnCallback(static fn(string $id): bool => array_key_exists($id, $properties));
        $stub->method('get')->willReturnCallback(static fn(string $id): mixed => $properties[$id] ?? null);

        return $stub;
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
