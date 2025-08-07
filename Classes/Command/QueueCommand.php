<?php

declare(strict_types=1);

namespace Lochmueller\Index\Command;

use Lochmueller\Index\Configuration\ConfigurationLoader;
use Lochmueller\Index\Enums\IndexTechnology;
use Lochmueller\Index\Indexing\Database\DatabaseIndexing;
use Lochmueller\Index\Indexing\File\FileIndexing;
use Lochmueller\Index\Indexing\Frontend\FrontendIndexing;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use TYPO3\CMS\Core\Site\SiteFinder;
use TYPO3\CMS\Core\Utility\GeneralUtility;

#[AsCommand(
    name: 'index:queue',
    description: 'Add the right entries to the message queue to trigger the index process of the different configurations',
)]
class QueueCommand extends Command
{
    public function __construct(
        private readonly SiteFinder          $siteFinder,
        private readonly ConfigurationLoader $configurationLoader,
        private readonly FileIndexing        $fileIndexing,
        private readonly DatabaseIndexing    $databaseIndex,
        private readonly FrontendIndexing    $frontendIndex,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('limitSiteIdentifiers', null, InputOption::VALUE_REQUIRED, 'Limit to site identifiers seperated by comma (,). Empty string -> all sites are checked', '');
        $this->addOption('limitConfigurationIdentifiers', null, InputOption::VALUE_REQUIRED, 'Limit to configuration record UIDs seperated by comma (,). Empty string -> all configurations are checked. This is more important than the limitation to sites.', '');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $configurations = $this->getLimitedConfigurationUids($input);
        if (empty($configurations)) {
            $sites = $this->getRelevantSites($input);
            foreach ($sites as $site) {
                $siteConfig = $this->configurationLoader->loadBySite($site);
                if ($siteConfig) {
                    $configurations[] = $siteConfig;
                }
            }
        }

        foreach ($configurations as $configurationUid) {
            $configuration = is_int($configurationUid) ? $this->configurationLoader->loadByUid($configurationUid) : $configurationUid;
            if ($configuration->technology === IndexTechnology::Database) {
                $this->databaseIndex->fillQueue($configuration);
            } elseif ($configuration->technology === IndexTechnology::Frontend) {
                $this->frontendIndex->fillQueue($configuration);
            }
            $this->fileIndexing->fillQueue($configuration->fileMounts, $configuration->fileTypes);
        }

        return Command::SUCCESS;

    }


    private function getRelevantSites(InputInterface $input): array
    {
        $siteIdentifiers = GeneralUtility::trimExplode(',', (string) $input->getOption('limitSiteIdentifiers'), true);
        if (empty($siteIdentifiers)) {
            return $this->siteFinder->getAllSites();
        }
        return array_map(function ($siteId) {
            return $this->siteFinder->getSiteByIdentifier($siteId);
        }, $siteIdentifiers);
    }

    private function getLimitedConfigurationUids(InputInterface $input): array
    {
        return GeneralUtility::trimExplode(',', (string) $input->getOption('limitConfigurationIdentifiers'), true);
    }
}
