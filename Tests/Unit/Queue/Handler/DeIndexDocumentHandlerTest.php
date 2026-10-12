<?php

declare(strict_types=1);

namespace Lochmueller\Index\Tests\Unit\Queue\Handler;

use Lochmueller\Index\Event\DeIndexDocumentEvent;
use Lochmueller\Index\Queue\Handler\DeIndexDocumentHandler;
use Lochmueller\Index\Queue\Message\DeIndexDocumentMessage;
use Lochmueller\Index\Tests\Unit\AbstractTest;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;
use TYPO3\CMS\Core\Exception\SiteNotFoundException;
use TYPO3\CMS\Core\Http\Uri;
use TYPO3\CMS\Core\Routing\InvalidRouteArgumentsException;
use TYPO3\CMS\Core\Routing\RouterInterface;
use TYPO3\CMS\Core\Site\Entity\Site;
use TYPO3\CMS\Core\Site\Entity\SiteLanguage;
use TYPO3\CMS\Core\Site\SiteFinder;

class DeIndexDocumentHandlerTest extends AbstractTest
{
    public function testEventIsDispatchedWithGeneratedUri(): void
    {
        $language = $this->createStub(SiteLanguage::class);

        $router = $this->createMock(RouterInterface::class);
        $router->expects(self::once())
            ->method('generateUri')
            ->with(42, ['_language' => $language])
            ->willReturn(new Uri('https://example.com/de/page'));

        $site = $this->createMock(Site::class);
        $site->expects(self::once())->method('getLanguageById')->with(1)->willReturn($language);
        $site->method('getRouter')->willReturn($router);

        $siteFinder = $this->createMock(SiteFinder::class);
        $siteFinder->expects(self::once())->method('getSiteByPageId')->with(42)->willReturn($site);

        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $eventDispatcher->expects(self::once())
            ->method('dispatch')
            ->with(self::callback(static fn(object $event): bool => $event instanceof DeIndexDocumentEvent
                && $event->site === $site
                && $event->uri === 'https://example.com/de/page'))
            ->willReturnArgument(0);

        $subject = new DeIndexDocumentHandler($siteFinder, $eventDispatcher);

        $subject(new DeIndexDocumentMessage(pageUid: 42, languageId: 1));
    }

    public function testEventIsDispatchedWithResolvedUriOfMessage(): void
    {
        $site = $this->createStub(Site::class);

        $siteFinder = $this->createMock(SiteFinder::class);
        $siteFinder->expects(self::once())->method('getSiteByIdentifier')->with('main')->willReturn($site);
        $siteFinder->expects(self::never())->method('getSiteByPageId');

        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $eventDispatcher->expects(self::once())
            ->method('dispatch')
            ->with(self::callback(static fn(object $event): bool => $event instanceof DeIndexDocumentEvent
                && $event->site === $site
                && $event->uri === 'https://example.com/old-slug'))
            ->willReturnArgument(0);

        $subject = new DeIndexDocumentHandler($siteFinder, $eventDispatcher);

        $subject(new DeIndexDocumentMessage(pageUid: 42, languageId: 0, uri: 'https://example.com/old-slug', siteIdentifier: 'main'));
    }

    public function testUnexpectedExceptionIsLogged(): void
    {
        $exception = new \RuntimeException('Unexpected', 1234567893);

        $siteFinder = $this->createStub(SiteFinder::class);
        $siteFinder->method('getSiteByIdentifier')->willThrowException($exception);

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())
            ->method('error')
            ->with($exception->getMessage(), ['exception' => $exception]);

        $subject = new DeIndexDocumentHandler($siteFinder, $this->createStub(EventDispatcherInterface::class));
        $subject->setLogger($logger);

        $subject(new DeIndexDocumentMessage(pageUid: 42, languageId: 0, uri: 'https://example.com/page', siteIdentifier: 'main'));
    }

    public function testExceptionIsLoggedWhenSiteIsNotFound(): void
    {
        $exception = new SiteNotFoundException('No site found for page 42', 1234567890);

        $siteFinder = $this->createStub(SiteFinder::class);
        $siteFinder->method('getSiteByPageId')->willThrowException($exception);

        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $eventDispatcher->expects(self::never())->method('dispatch');

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())
            ->method('error')
            ->with($exception->getMessage(), ['exception' => $exception]);

        $subject = new DeIndexDocumentHandler($siteFinder, $eventDispatcher);
        $subject->setLogger($logger);

        $subject(new DeIndexDocumentMessage(pageUid: 42, languageId: 0));
    }

    public function testExceptionIsLoggedWhenLanguageIsInvalid(): void
    {
        $exception = new \InvalidArgumentException('Language 99 does not exist', 1234567891);

        $site = $this->createStub(Site::class);
        $site->method('getLanguageById')->willThrowException($exception);

        $siteFinder = $this->createStub(SiteFinder::class);
        $siteFinder->method('getSiteByPageId')->willReturn($site);

        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $eventDispatcher->expects(self::never())->method('dispatch');

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())
            ->method('error')
            ->with($exception->getMessage(), ['exception' => $exception]);

        $subject = new DeIndexDocumentHandler($siteFinder, $eventDispatcher);
        $subject->setLogger($logger);

        $subject(new DeIndexDocumentMessage(pageUid: 42, languageId: 99));
    }

    public function testExceptionIsLoggedWhenUriCannotBeGenerated(): void
    {
        $exception = new InvalidRouteArgumentsException('Invalid route arguments', 1234567892);

        $router = $this->createStub(RouterInterface::class);
        $router->method('generateUri')->willThrowException($exception);

        $site = $this->createStub(Site::class);
        $site->method('getLanguageById')->willReturn($this->createStub(SiteLanguage::class));
        $site->method('getRouter')->willReturn($router);

        $siteFinder = $this->createStub(SiteFinder::class);
        $siteFinder->method('getSiteByPageId')->willReturn($site);

        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $eventDispatcher->expects(self::never())->method('dispatch');

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())
            ->method('error')
            ->with($exception->getMessage(), ['exception' => $exception]);

        $subject = new DeIndexDocumentHandler($siteFinder, $eventDispatcher);
        $subject->setLogger($logger);

        $subject(new DeIndexDocumentMessage(pageUid: 42, languageId: 0));
    }
}
