<?php

declare(strict_types=1);

namespace Lochmueller\Index\Traversing;

use Lochmueller\Index\Configuration\Configuration;
use Lochmueller\Index\Configuration\ConfigurationLoader;
use Lochmueller\Index\Domain\Repository\PagesRepository;
use Lochmueller\Index\Utility\AccessGroupParser;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;
use TYPO3\CMS\Core\Site\SiteFinder;

class PageTraversing
{
    /**
     * @param iterable<Extender\ExtenderInterface> $extender
     */
    public function __construct(
        private SiteFinder            $siteFinder,
        #[AutowireIterator('index.extender')]
        protected iterable            $extender,
        protected ConfigurationLoader $configurationLoader,
        protected RecordSelection     $recordSelection,
        protected PagesRepository     $pagesRepository,
    ) {}

    /**
     * @return iterable<FrontendInformationDto>
     */
    public function getFrontendInformation(Configuration $configuration): iterable
    {
        $site = $this->siteFinder->getSiteByPageId($configuration->pageId);
        $extenderConfiguration = $configuration->configuration['extender'] ?? [];
        $router = $site->getRouter();

        $targetLanguages = [];
        $languages = $site->getLanguages();
        if (empty($configuration->languages)) {
            $targetLanguages = $languages;
        } else {
            foreach ($configuration->languages as $languageId) {
                foreach ($languages as $language) {
                    if ($language->getLanguageId() === $languageId) {
                        $targetLanguages[$language->getLanguageId()] = $language;
                    }
                }
            }
        }

        foreach ($this->getRelevantPageUids($configuration) as $relevantPageUid) {
            foreach ($targetLanguages as $language) {
                $row = $this->recordSelection->findRenderablePage($relevantPageUid, $language->getLanguageId());
                if ($row === null) {
                    continue;
                }

                foreach ($extenderConfiguration as $item) {
                    $dropOriginalUri = isset($item['dropOriginalUri']) && $item['dropOriginalUri'];
                    if (!isset($item['limitToPages']) || (is_array($item['limitToPages']) && in_array($relevantPageUid, $item['limitToPages'], true))) {
                        // Handled via extender
                        foreach ($this->extender as $extender) {
                            if ($extender->getName() === ($item['type'] ?? '')) {
                                yield from $extender->getItems($configuration, $item, $site, $relevantPageUid, $language, $row);
                                if ($dropOriginalUri) {
                                    continue 3;
                                }
                                continue 2;
                            }
                        }
                    }
                }

                $arguments = ['_language' => $language];

                yield new FrontendInformationDto(
                    uri: $router->generateUri($relevantPageUid, $arguments),
                    arguments: $arguments,
                    pageUid: $relevantPageUid,
                    language: $language,
                    row: $row,
                    accessGroups: AccessGroupParser::parse($row['fe_group'] ?? ''),
                );
            }
        }
    }

    /**
     * @return iterable<int>
     */
    protected function getRelevantPageUids(Configuration $configuration, ?int $id = null, ?int $depth = null): iterable
    {
        if ($id === null) {
            $id = $configuration->pageId;
        }
        if ($depth === null) {
            $depth = $configuration->levels;
        }

        $pageConfiguration = $this->configurationLoader->loadByPage($id);
        if ($pageConfiguration instanceof Configuration && $pageConfiguration->configurationId !== $configuration->configurationId) {
            // Stop traversing on new configurations
            return;
        }
        yield $id;
        if ($id && $depth > 0) {
            foreach ($this->pagesRepository->findChildPageUids($id) as $childUid) {
                yield from $this->getRelevantPageUids($configuration, $childUid, $depth - 1);
            }
        }
    }


}
