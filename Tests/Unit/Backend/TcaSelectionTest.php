<?php

declare(strict_types=1);

namespace Lochmueller\Index\Tests\Unit\Backend;

use Lochmueller\Index\Backend\TcaSelection;
use Lochmueller\Index\Tests\Unit\AbstractTest;
use TYPO3\CMS\Core\Site\Entity\SiteInterface;
use TYPO3\CMS\Core\Site\Entity\SiteLanguage;

class TcaSelectionTest extends AbstractTest
{
    private TcaSelection $subject;

    protected function setUp(): void
    {
        parent::setUp();
        $this->subject = new TcaSelection();
    }

    public function testCountrySelectionReturnsEarlyWhenSiteIsNotSet(): void
    {
        $params = [];

        $this->subject->countrySelection($params);

        self::assertArrayNotHasKey('items', $params);
    }

    public function testCountrySelectionReturnsEarlyWhenSiteIsNotSiteInterface(): void
    {
        $params = ['site' => 'not-a-site-interface'];

        $this->subject->countrySelection($params);

        self::assertArrayNotHasKey('items', $params);
    }

    public function testCountrySelectionAddsLanguagesFromSite(): void
    {
        $language1 = $this->createMock(SiteLanguage::class);
        $language1->method('getTitle')->willReturn('English');
        $language1->method('getLanguageId')->willReturn(0);
        $language1->method('getFlagIdentifier')->willReturn('flags-gb');

        $language2 = $this->createMock(SiteLanguage::class);
        $language2->method('getTitle')->willReturn('German');
        $language2->method('getLanguageId')->willReturn(1);
        $language2->method('getFlagIdentifier')->willReturn('flags-de');

        $site = $this->createMock(SiteInterface::class);
        $site->method('getLanguages')->willReturn([$language1, $language2]);

        $params = ['site' => $site];

        $this->subject->countrySelection($params);

        self::assertCount(2, $params['items']);
        self::assertSame([
            'label' => 'English',
            'value' => 0,
            'icon' => 'flags-gb',
        ], $params['items'][0]);
        self::assertSame([
            'label' => 'German',
            'value' => 1,
            'icon' => 'flags-de',
        ], $params['items'][1]);
    }

    public function testCountrySelectionAppendsToExistingItems(): void
    {
        $language = $this->createMock(SiteLanguage::class);
        $language->method('getTitle')->willReturn('French');
        $language->method('getLanguageId')->willReturn(2);
        $language->method('getFlagIdentifier')->willReturn('flags-fr');

        $site = $this->createMock(SiteInterface::class);
        $site->method('getLanguages')->willReturn([$language]);

        $params = [
            'site' => $site,
            'items' => [
                ['label' => 'Existing', 'value' => 99, 'icon' => 'existing-icon'],
            ],
        ];

        $this->subject->countrySelection($params);

        self::assertCount(2, $params['items']);
        self::assertSame('Existing', $params['items'][0]['label']);
        self::assertSame('French', $params['items'][1]['label']);
    }

    public function testCountrySelectionHandlesEmptyLanguages(): void
    {
        $site = $this->createMock(SiteInterface::class);
        $site->method('getLanguages')->willReturn([]);

        $params = ['site' => $site];

        $this->subject->countrySelection($params);

        self::assertArrayNotHasKey('items', $params);
    }
}
