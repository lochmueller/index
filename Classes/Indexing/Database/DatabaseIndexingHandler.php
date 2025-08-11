<?php

declare(strict_types=1);

namespace Lochmueller\Index\Indexing\Database;

use Lochmueller\Index\Event\IndexPageEvent;
use Lochmueller\Index\Indexing\IndexingInterface;
use Lochmueller\Index\Queue\Message\DatabaseIndexMessage;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Http\Message\ServerRequestInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use TYPO3\CMS\Core\Context\Context;
use TYPO3\CMS\Core\Context\LanguageAspectFactory;
use TYPO3\CMS\Core\Domain\RecordFactory;
use TYPO3\CMS\Core\Http\ApplicationType;
use TYPO3\CMS\Core\Http\NormalizedParams;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Core\Http\Uri;
use TYPO3\CMS\Core\Site\Entity\Site;
use TYPO3\CMS\Core\Site\Entity\SiteLanguage;
use TYPO3\CMS\Core\Site\SiteFinder;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Frontend\ContentObject\ContentObjectRenderer;
use TYPO3\CMS\Frontend\Page\PageInformation;

class DatabaseIndexingHandler implements IndexingInterface
{
    public function __construct(
        private SiteFinder                        $siteFinder,
        private RecordFactory                     $recordFactory,
        private ContentIndexing                   $contentIndexing,
        private readonly LanguageAspectFactory    $languageAspectFactory,
        private readonly Context                  $context,
        private readonly EventDispatcherInterface $eventDispatcher,
    ) {}

    #[AsMessageHandler]
    public function __invoke(DatabaseIndexMessage $message): void
    {
        /** @var ContentObjectRenderer $cObj */
        $cObj = GeneralUtility::makeInstance(ContentObjectRenderer::class);
        $cObj->start([], 'tt_content');

        $site = $this->siteFinder->getSiteByIdentifier($message->siteIdentifier);

        $cObj->setRequest($this->buildFrontendRequest($site, $site->getDefaultLanguage()));

        $records = $cObj->getRecords('tt_content', [
            'pidInList' => $message->pageUid,
        ]);
        foreach ($records as $key => $record) {
            $records[$key] = $this->recordFactory->createResolvedRecordFromDatabaseRow('tt_content', $record);
        }

        $title = ''; // @todo
        $language = 0; // @todo
        $accessGroups = []; // @todo

        $mainContent = '';
        foreach ($records as $record) {
            $mainContent .= $this->contentIndexing->getContent($record);
        }

        $this->eventDispatcher->dispatch(new IndexPageEvent(
            site: $site,
            technology: $message->technology,
            type: $message->type,
            indexConfigurationRecordId: $message->indexConfigurationRecordId,
            language: $language,
            title: $title,
            content: $mainContent,
            pageUid: $message->pageUid,
            accessGroups: $accessGroups,
        ));
    }

    private function buildFrontendRequest(Site $site, SiteLanguage $siteLanguage): ServerRequestInterface
    {
        // Basis-URI aus der SiteLanguage (Domain + Pfadpräfix)
        $uri = new Uri((string) $siteLanguage->getBase() . 'team');

        $pageInfo = new PageInformation();
        $pageInfo->setId(11);
        $pageInfo->setContentFromPid(19);

        $request = (new ServerRequest((string) $uri, 'GET'))
            ->withAttribute('applicationType', ApplicationType::FRONTEND)
            ->withAttribute('site', $site)
            ->withAttribute('frontend.page.information', $pageInfo)
            ->withAttribute('siteLanguage', $siteLanguage);

        // NormalizedParams (einige APIs erwarten das Attribut)
        $normalized = NormalizedParams::createFromRequest($request);
        $request = $request->withAttribute('normalizedParams', $normalized);

        // Context: LanguageAspect passend zur SiteLanguage setzen
        $languageAspect = $this->languageAspectFactory->createFromSiteLanguage($siteLanguage);
        $this->context->setAspect('language', $languageAspect);

        // (Optional) FE-User, falls benötigt:
        // $request = $request->withAttribute('frontend.user', $frontendUser);

        return $request;
    }
}
