<?php

declare(strict_types=1);

namespace Lochmueller\Index\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use TYPO3\CMS\Core\Site\Entity\Site;
use TYPO3\CMS\Core\Site\SiteFinder;
use TYPO3\CMS\Core\Utility\GeneralUtility;

#[AsCommand(
    name: 'index:queue',
    description: 'Add the right entries to the message queue to trigger the index process of the different configurations',
)]
class QueueCommand extends Command
{
    public function __construct(
        private SiteFinder $siteFinder,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('limitSiteIdentifiers', null, InputOption::VALUE_REQUIRED, 'Limit to site identifiers seperated by comma (,). Empty string -> all sites are checked', '');
        $this->addOption('limitConfigurationIdentifiers', null, InputOption::VALUE_REQUIRED, 'Limit to configuration record UIDs seperated by comma (,). Empty string -> all configurations are checked', '');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $sites = $this->getRelevantSites($input);

        // @todo integrate

        foreach ($sites as $site) {
            /** @var Site $site */
            $indexType = $site->getConfiguration()['sealIndexType'] ?? '';
            if ($indexType === 'database') {
                $this->databaseIndex->indexDatabase($site);
            } elseif ($indexType === 'web') {
                $this->webIndex->fillQueueForWebIndex($site);
            }
        }

        return Command::SUCCESS;
    }

    private function getRelevantSites(InputInterface $input): array
    {
        $siteIdentifiers = GeneralUtility::trimExplode(',', (string) $input->getArgument('siteIdentifiers'), true);
        if (empty($siteIdentifiers)) {
            return $this->siteFinder->getAllSites();
        }
        return array_map(function ($siteId) {
            return $this->siteFinder->getSiteByIdentifier($siteId);
        }, $siteIdentifiers);
    }
}
