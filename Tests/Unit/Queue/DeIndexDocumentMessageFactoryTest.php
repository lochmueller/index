<?php

declare(strict_types=1);

namespace Lochmueller\Index\Tests\Unit\Queue;

use Lochmueller\Index\Domain\Repository\PagesRepository;
use Lochmueller\Index\Queue\DeIndexDocumentMessageFactory;
use Lochmueller\Index\Tests\Unit\AbstractTest;
use TYPO3\CMS\Core\Exception\SiteNotFoundException;
use TYPO3\CMS\Core\Http\Uri;
use TYPO3\CMS\Core\Routing\RouterInterface;
use TYPO3\CMS\Core\Site\Entity\Site;
use TYPO3\CMS\Core\Site\Entity\SiteLanguage;
use TYPO3\CMS\Core\Site\SiteFinder;

class DeIndexDocumentMessageFactoryTest extends AbstractTest
{
    private const DEFAULT_ROW = ['uid' => 10, 'sys_language_uid' => 0, 'l10n_parent' => 0, 'slug' => '/page', 'hidden' => 1];
    private const TRANSLATION_ROW = ['uid' => 11, 'sys_language_uid' => 1, 'l10n_parent' => 10, 'slug' => '/seite'];

    public function testCreateForPageReturnsDocumentsOfAllLanguages(): void
    {
        /** @var array<int, mixed> $routes */
        $routes = [];
        $subject = $this->createSubject($routes);

        $messages = $subject->createForPage(10);

        self::assertSame([0, 1], array_keys($messages));
        self::assertSame('https://example.com/page', $messages[0]->uri);
        self::assertSame('https://example.com/seite', $messages[1]->uri);
        self::assertSame(10, $messages[1]->pageUid);
        self::assertSame(1, $messages[1]->languageId);
        self::assertSame('main', $messages[1]->siteIdentifier);

        // The router gets the records, so hidden pages are not looked up again
        self::assertSame(self::DEFAULT_ROW, $routes[0]);
        self::assertSame(10, $routes[1]['uid']);
        self::assertSame(11, $routes[1]['_LOCALIZED_UID']);
    }

    public function testCreateForPageReturnsOnlyRequestedLanguage(): void
    {
        $routes = [];
        $subject = $this->createSubject($routes);

        self::assertSame([1], array_keys($subject->createForPage(10, 1)));
        self::assertSame([0], array_keys($subject->createForPage(10, 0)));
    }

    public function testCreateForPageReturnsEmptyArrayWithoutSite(): void
    {
        $siteFinder = $this->createStub(SiteFinder::class);
        $siteFinder->method('getSiteByPageId')->willThrowException(new SiteNotFoundException('No site', 1));

        $subject = new DeIndexDocumentMessageFactory($siteFinder, $this->createStub(PagesRepository::class));

        self::assertSame([], $subject->createForPage(10));
    }

    public function testCreateForPageReturnsEmptyArrayWithoutPage(): void
    {
        $siteFinder = $this->createStub(SiteFinder::class);
        $siteFinder->method('getSiteByPageId')->willReturn($this->createStub(Site::class));

        $pagesRepository = $this->createStub(PagesRepository::class);
        $pagesRepository->method('findByUid')->willReturn(null);

        $subject = new DeIndexDocumentMessageFactory($siteFinder, $pagesRepository);

        self::assertSame([], $subject->createForPage(10));
    }

    public function testCreateForPageSkipsUnknownLanguages(): void
    {
        $site = $this->createStub(Site::class);
        $site->method('getIdentifier')->willReturn('main');
        $site->method('getRouter')->willReturn($this->createStub(RouterInterface::class));
        $site->method('getLanguageById')->willThrowException(new \InvalidArgumentException('Unknown language', 1));

        $siteFinder = $this->createStub(SiteFinder::class);
        $siteFinder->method('getSiteByPageId')->willReturn($site);

        $pagesRepository = $this->createStub(PagesRepository::class);
        $pagesRepository->method('findByUid')->willReturn(self::DEFAULT_ROW);
        $pagesRepository->method('findTranslations')->willReturn([]);

        $subject = new DeIndexDocumentMessageFactory($siteFinder, $pagesRepository);

        self::assertSame([], $subject->createForPage(10));
    }

    /**
     * @param array<int, mixed> $routes
     */
    private function createSubject(array &$routes): DeIndexDocumentMessageFactory
    {
        $router = $this->createStub(RouterInterface::class);
        $router->method('generateUri')->willReturnCallback(static function (array $route) use (&$routes): Uri {
            $routes[(int) $route['sys_language_uid']] = $route;
            return new Uri('https://example.com' . $route['slug']);
        });

        $site = $this->createStub(Site::class);
        $site->method('getIdentifier')->willReturn('main');
        $site->method('getRouter')->willReturn($router);
        $site->method('getLanguageById')->willReturn($this->createStub(SiteLanguage::class));

        $siteFinder = $this->createStub(SiteFinder::class);
        $siteFinder->method('getSiteByPageId')->willReturn($site);

        $pagesRepository = $this->createStub(PagesRepository::class);
        $pagesRepository->method('findByUid')->willReturn(self::DEFAULT_ROW);
        $pagesRepository->method('findTranslations')->willReturnCallback(static fn(): iterable => [self::TRANSLATION_ROW]);

        return new DeIndexDocumentMessageFactory($siteFinder, $pagesRepository);
    }
}
