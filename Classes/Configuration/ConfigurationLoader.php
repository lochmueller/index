<?php

declare(strict_types=1);

namespace Lochmueller\Index\Configuration;

use Lochmueller\Index\Domain\Repository\ConfigurationRepository;
use Lochmueller\Index\Domain\Repository\PagesRepository;
use TYPO3\CMS\Core\Site\Entity\SiteInterface;

class ConfigurationLoader
{
    /** @var array<int, Configuration> */
    protected static array $runtimeConfigurationCache = [];
    protected static bool $preloadExecuted = false;

    public function __construct(
        protected readonly ConfigurationRepository $configurationRepository,
        protected readonly PagesRepository $pagesRepository,
    ) {}

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

        foreach ($this->pagesRepository->getRootline($pageUid) as $page) {
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

    /**
     * @return iterable<Configuration>
     */
    public function loadAllBySite(SiteInterface $site): iterable
    {
        $this->preloadConfigurations();
        $rootPageId = $site->getRootPageId();
        foreach (self::$runtimeConfigurationCache as $configuration) {
            $rootLineIds = $this->pagesRepository->getRootlineIds($configuration->pageId);
            if (in_array($rootPageId, $rootLineIds, true)) {
                yield $configuration;
            }
        }
    }

    /**
     * @return array<int, Configuration>
     */
    public function getAll(): array
    {
        $this->preloadConfigurations();
        return self::$runtimeConfigurationCache;
    }

    public function preloadConfigurations(): void
    {
        if (!self::$preloadExecuted) {
            foreach ($this->configurationRepository->findAll() as $item) {
                self::$runtimeConfigurationCache[(int) $item['uid']] = Configuration::createByDatabaseRow($item);
            }
            self::$preloadExecuted = true;
        }
    }
}
