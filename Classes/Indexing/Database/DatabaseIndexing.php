<?php

declare(strict_types=1);

namespace Lochmueller\Index\Indexing\Database;

use Lochmueller\Index\Configuration\Configuration;
use Lochmueller\Index\Enums\IndexTechnology;
use Lochmueller\Index\Enums\IndexType;
use Lochmueller\Index\Indexing\File\FileIndexing;
use Lochmueller\Index\Indexing\IndexingInterface;
use Lochmueller\Index\Queue\Message\CachePageMessage;
use Lochmueller\Index\Queue\Message\DatabaseIndexMessage;
use Lochmueller\Index\Queue\Message\FinishProcessMessage;
use Lochmueller\Index\Queue\Message\StartProcessMessage;
use Lochmueller\Index\Traversing\PageTraversing;
use Psr\Http\Message\ServerRequestInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Yaml\Yaml;
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
use TYPO3\CMS\Frontend\ContentObject\ContentDataProcessor;
use TYPO3\CMS\Frontend\ContentObject\ContentObjectRenderer;
use TYPO3\CMS\Frontend\Page\PageInformation;

class DatabaseIndexing implements IndexingInterface
{
    public function __construct(
        private MessageBusInterface $bus,
        private SiteFinder          $siteFinder,
        private PageTraversing      $pageTraversing,
        private FileIndexing        $fileIndexing,
        private RecordFactory       $recordFactory,
        private ContentIndexing $contentIndexing,
        private readonly ContentDataProcessor $contentDataProcessor,
        private readonly LanguageAspectFactory $languageAspectFactory,
        private readonly Context $context,
    ) {}

    public function fillQueue(Configuration $configuration): void
    {
        $site = $this->siteFinder->getSiteByPageId($configuration->pageId);

        $id = uniqid('cache-index', true);
        $this->bus->dispatch(new StartProcessMessage(
            siteIdentifier: $site->getIdentifier(),
            technology: IndexTechnology::Database,
            type: IndexType::Full,
            indexConfigurationRecordId: $configuration->configurationId,
            indexProcessId: $id,
        ));


        $indexConfiguration = Yaml::parse($configuration->configurationYaml);

        // @todo use file traversing and



        // $this->recordFactory->createRawRecord('pages', )


        // Test Start


        /** @var ContentObjectRenderer $cObj */
        $cObj = GeneralUtility::makeInstance(ContentObjectRenderer::class);
        $cObj->start([], 'tt_content');


        $cObj->setRequest($this->buildFrontendRequest($site, $site->getDefaultLanguage()));

        $records = $cObj->getRecords('tt_content', [
            'pidInList' => '11',
        ]);
        foreach ($records as $key => $record) {
            $records[$key] = $this->recordFactory->createResolvedRecordFromDatabaseRow('tt_content', $record);
        }




        foreach ($records as $key => $record) {
            $content = $this->contentIndexing->getContent($record);
            // var_dump($content);
        }




        $this->bus->dispatch(new DatabaseIndexMessage('test'));
        /*$this->bus->dispatch(new CachePageMessage(
            siteIdentifier: $site->getIdentifier(),
            technology: IndexTechnology::Cache,
            type: IndexType::Partial,
            indexConfigurationRecordId: $configuration->configurationId,
            language: (int)$this->context->getAspect('language')->getId(),
            title: $this->pageTitleProviderManager->getTitle($request),
            content: $tsfe->content,
            pageUid: (int)$pageInformation->getId(),
            accessGroups: $this->context->getPropertyFromAspect('frontend.user', 'groupIds', [0, -1]),
        ));*/

        $this->fileIndexing->fillQueue($configuration);

        $this->bus->dispatch(new FinishProcessMessage(
            siteIdentifier: $site->getIdentifier(),
            technology: IndexTechnology::Database,
            type: IndexType::Full,
            indexConfigurationRecordId: $configuration->configurationId,
            indexProcessId: $id,
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


    #[AsMessageHandler]
    public function handleMessage(DatabaseIndexMessage $message): void
    {

        // var_dump('HANDLE DB Index');

        // Record!!!!
        // $this->recordFactory->createRawRecord('pages', )
        // @todo integrate

        // RECORD API
    }

}
