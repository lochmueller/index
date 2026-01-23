<?php

declare(strict_types=1);

namespace Lochmueller\Index\Backend;

use TYPO3\CMS\Core\Site\Entity\SiteInterface;

class TcaSelection
{
    /**
     * @param array<string, mixed> $params
     */
    public function countrySelection(array &$params): void
    {

        if (!($params['site'] ?? null) instanceof SiteInterface) {
            return;
        }
        /** @var SiteInterface $site */
        $site = $params['site'];

        foreach ($site->getLanguages() as $siteLanguage) {
            $params['items'][] = [
                'label' => $siteLanguage->getTitle(),
                'value' => $siteLanguage->getLanguageId(),
                'icon' => $siteLanguage->getFlagIdentifier(),
            ];
        }

    }

}
