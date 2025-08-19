<?php

declare(strict_types=1);

namespace Lochmueller\Index\Configuration;

use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\Restriction\FrontendRestrictionContainer;
use TYPO3\CMS\Core\Site\Entity\SiteInterface;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Utility\RootlineUtility;

class ConfigurationLoader
{
    protected static ?array $runtimeConfigurationCache = null;

    public function loadByPage(int $pageUid): ?Configuration
    {
        $this->preloadConfigurations();
        foreach (self::$runtimeConfigurationCache as $configuration) {
            if ($configuration->pageId === $pageUid) {
                return $configuration;
            }
        }

        return null;
    }

    public function loadByPageTraversing(int $pageUid): ?Configuration
    {
        $this->preloadConfigurations();
        foreach (self::$runtimeConfigurationCache as $configuration) {
            if ($configuration->pageId === $pageUid) {
                return $configuration;
            }
        }

        /** @var RootlineUtility $rootLineUtility */
        $rootLineUtility = GeneralUtility::makeInstance(RootlineUtility::class, $pageUid);
        foreach ($rootLineUtility->get() as $page) {
            foreach (self::$runtimeConfigurationCache as $configuration) {
                if ($configuration->pageId === (int) $page['uid']) {
                    return $configuration;
                }
            }
        }

        return null;
    }

    public function loadByUid(int $uid): ?Configuration
    {
        $this->preloadConfigurations();
        return self::$runtimeConfigurationCache[$uid] ?? null;
    }

    public function loadBySite(SiteInterface $site): ?Configuration
    {
        return $this->loadByPage($site->getRootPageId());
    }

    public function loadAllBySite(SiteInterface $site): iterable
    {
        $this->preloadConfigurations();
        $rootPageId = $site->getRootPageId();
        foreach (self::$runtimeConfigurationCache as $configuration) {
            $rootLineUtility = GeneralUtility::makeInstance(RootlineUtility::class, $configuration->pageId);
            $rootLineIds = array_map(function ($entry) {
                return $entry['uid'];
            }, $rootLineUtility->get());
            if (in_array($rootPageId, $rootLineIds)) {
                yield $configuration;
            }
        }
    }

    public function getAll(): array
    {
        $this->preloadConfigurations();
        return self::$runtimeConfigurationCache;
    }

    public function preloadConfigurations(): void
    {
        if (self::$runtimeConfigurationCache === null) {
            self::$runtimeConfigurationCache = [];
            $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable('tx_index_domain_model_configuration');
            $queryBuilder->getRestrictions()->removeAll();
            $queryBuilder->getRestrictions()->add(GeneralUtility::makeInstance(FrontendRestrictionContainer::class));
            $result = $queryBuilder->select('*')
                ->from('tx_index_domain_model_configuration')
                ->executeQuery();

            foreach ($result->iterateAssociative() as $item) {
                self::$runtimeConfigurationCache[(int) $item['uid']] = Configuration::createByDatabaseRow($item);
            }
        }
    }

}
