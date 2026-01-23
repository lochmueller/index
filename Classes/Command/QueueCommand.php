<?php

declare(strict_types=1);

namespace Lochmueller\Index\Command;

use Lochmueller\Index\Configuration\ConfigurationLoader;
use Lochmueller\Index\Indexing\ActiveIndexing;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use TYPO3\CMS\Core\Site\SiteFinder;
use TYPO3\CMS\Core\Utility\GeneralUtility;

#[AsCommand(
    name: 'index:queue',
    description: 'Add the right entries to the message queue to trigger the index process of the different configurations.',
)]
class QueueCommand extends Command
{
    public function __construct(
        private readonly SiteFinder            $siteFinder,
        private readonly ConfigurationLoader   $configurationLoader,
        private readonly ActiveIndexing $activeIndexing,
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
        foreach ($this->getConfigurations($input) as $configuration) {
            $this->activeIndexing->fillQueue($configuration);
        }

        return Command::SUCCESS;
    }

    /**
     * @return iterable<\Lochmueller\Index\Configuration\Configuration>
     */
    protected function getConfigurations(InputInterface $input): iterable
    {
        $configurations = $this->getLimitedConfigurationUids($input);
        if (!empty($configurations)) {
            foreach ($configurations as $uid) {
                yield $this->configurationLoader->loadByUid($uid);
            }
            return;
        }

        $sites = $this->getRelevantSites($input);
        foreach ($sites as $site) {
            yield from $this->configurationLoader->loadAllBySite($site);
        }
    }


    /**
     * @return iterable<\TYPO3\CMS\Core\Site\Entity\Site>
     */
    private function getRelevantSites(InputInterface $input): iterable
    {
        $siteIdentifiers = GeneralUtility::trimExplode(',', (string) $input->getOption('limitSiteIdentifiers'), true);
        if (empty($siteIdentifiers)) {
            yield from $this->siteFinder->getAllSites();
        }
        foreach ($siteIdentifiers as $siteId) {
            yield $this->siteFinder->getSiteByIdentifier($siteId);
        }
    }

    /**
     * @return int[]
     */
    private function getLimitedConfigurationUids(InputInterface $input): array
    {
        return GeneralUtility::intExplode(',', (string) $input->getOption('limitConfigurationIdentifiers'), true);
    }
}
