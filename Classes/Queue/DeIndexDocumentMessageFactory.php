<?php

declare(strict_types=1);

namespace Lochmueller\Index\Queue;

use Lochmueller\Index\Domain\Repository\PagesRepository;
use Lochmueller\Index\Queue\Message\DeIndexDocumentMessage;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use TYPO3\CMS\Core\Site\SiteFinder;

/**
 * Builds the de-index messages for the documents (URIs) of a page in its current state.
 *
 * The URIs are generated out of the page records - and not via the page lookup of the router - because
 * hidden or expired pages are not found by the router and would end up as the URI of the site root.
 */
class DeIndexDocumentMessageFactory implements LoggerAwareInterface
{
    use LoggerAwareTrait;

    public function __construct(
        private readonly SiteFinder      $siteFinder,
        private readonly PagesRepository $pagesRepository,
    ) {}

    /**
     * @param int $pageUid Uid of the page in the default language
     * @param int|null $languageId Null for all languages of the page
     * @return array<int, DeIndexDocumentMessage> Keyed by language ID
     */
    public function createForPage(int $pageUid, ?int $languageId = null): array
    {
        try {
            $site = $this->siteFinder->getSiteByPageId($pageUid);
            $defaultRow = $this->pagesRepository->findByUid($pageUid);
        } catch (\Exception $exception) {
            $this->logger?->warning($exception->getMessage(), ['exception' => $exception, 'pageUid' => $pageUid]);
            return [];
        }
        if ($defaultRow === null) {
            return [];
        }

        $routes = [];
        if ($languageId === null || $languageId === 0) {
            $routes[0] = $defaultRow;
        }
        if ($languageId !== 0) {
            foreach ($this->pagesRepository->findTranslations($pageUid) as $translation) {
                $translationLanguageId = (int) $translation['sys_language_uid'];
                if ($languageId === null || $languageId === $translationLanguageId) {
                    // Same structure as a page overlay, so the router uses the record and the default page uid
                    $routes[$translationLanguageId] = array_replace($translation, [
                        'uid' => $pageUid,
                        '_LOCALIZED_UID' => (int) $translation['uid'],
                    ]);
                }
            }
        }

        $messages = [];
        foreach ($routes as $routeLanguageId => $route) {
            try {
                $uri = $site->getRouter()->generateUri($route, ['_language' => $site->getLanguageById($routeLanguageId)]);
            } catch (\Exception $exception) {
                $this->logger?->warning($exception->getMessage(), ['exception' => $exception, 'pageUid' => $pageUid, 'languageId' => $routeLanguageId]);
                continue;
            }
            $messages[$routeLanguageId] = new DeIndexDocumentMessage(
                pageUid: $pageUid,
                languageId: $routeLanguageId,
                uri: (string) $uri,
                siteIdentifier: $site->getIdentifier(),
            );
        }

        return $messages;
    }
}
