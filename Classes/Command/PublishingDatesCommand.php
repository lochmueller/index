<?php

declare(strict_types=1);

namespace Lochmueller\Index\Command;

use Lochmueller\Index\Configuration\Configuration;
use Lochmueller\Index\Configuration\ConfigurationLoader;
use Lochmueller\Index\Domain\Repository\GenericRepository;
use Lochmueller\Index\Indexing\ActiveIndexing;
use Lochmueller\Index\Queue\Bus;
use Lochmueller\Index\Queue\DeIndexDocumentMessageFactory;
use Lochmueller\Index\Utility\PageVisibility;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use TYPO3\CMS\Core\Registry;

/**
 * Reaching a starttime or endtime changes the frontend without a DataHandler operation, so no hook
 * updates the index. This command handles all records whose publishing date was reached since the last run.
 */
#[AsCommand(
    name: 'index:publishing-dates',
    description: 'De-index pages whose endtime was reached and partially index pages and content elements whose starttime or endtime was reached since the last run.',
)]
class PublishingDatesCommand extends Command
{
    private const REGISTRY_NAMESPACE = 'index';
    private const REGISTRY_KEY = 'publishingDatesLastRun';
    private const FIRST_RUN_RANGE = 86400;

    public function __construct(
        private readonly GenericRepository             $genericRepository,
        private readonly ConfigurationLoader           $configurationLoader,
        private readonly ActiveIndexing                $activeIndexing,
        private readonly DeIndexDocumentMessageFactory $deIndexDocumentMessageFactory,
        private readonly Bus                           $bus,
        private readonly Registry                      $registry,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $now = time();
        $lastRun = (int) $this->registry->get(self::REGISTRY_NAMESPACE, self::REGISTRY_KEY, $now - self::FIRST_RUN_RANGE);

        /** @var array<int, Configuration> $pagesToIndex */
        $pagesToIndex = [];
        $deIndexedDocuments = 0;

        foreach ($this->genericRepository->setTableName('pages')->findByPublishingDateRange($lastRun, $now) as $row) {
            $languageId = (int) ($row['sys_language_uid'] ?? 0);
            $pageUid = $languageId > 0 && (int) ($row['l10n_parent'] ?? 0) > 0 ? (int) $row['l10n_parent'] : (int) $row['uid'];
            $configuration = $this->configurationLoader->loadByPageTraversing($pageUid);
            if (!$configuration instanceof Configuration) {
                continue;
            }

            if (PageVisibility::isRenderable($row, $now)) {
                $pagesToIndex[$pageUid] = $configuration;
                continue;
            }

            // Translations are not rendered without the page in the default language
            foreach ($this->deIndexDocumentMessageFactory->createForPage($pageUid, $languageId === 0 ? null : $languageId) as $message) {
                $this->bus->dispatch($message);
                $deIndexedDocuments++;
            }
        }

        foreach ($this->genericRepository->setTableName('tt_content')->findByPublishingDateRange($lastRun, $now) as $row) {
            $pageUid = (int) $row['pid'];
            if (isset($pagesToIndex[$pageUid])) {
                continue;
            }
            $configuration = $this->configurationLoader->loadByPageTraversing($pageUid);
            if ($configuration instanceof Configuration) {
                $pagesToIndex[$pageUid] = $configuration;
            }
        }

        foreach ($pagesToIndex as $pageUid => $configuration) {
            $this->activeIndexing->fillQueue($configuration->modifyForPartialIndexing($pageUid), true);
        }

        $this->registry->set(self::REGISTRY_NAMESPACE, self::REGISTRY_KEY, $now);

        $output->writeln(sprintf('%d documents de-indexed, %d pages partially indexed.', $deIndexedDocuments, count($pagesToIndex)));

        return Command::SUCCESS;
    }
}
