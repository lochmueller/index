<?php

declare(strict_types=1);

namespace Lochmueller\Index\Indexing\Database;

use Lochmueller\Index\Configuration\Configuration;
use Lochmueller\Index\Indexing\IndexingInterface;
use Lochmueller\Index\Traversing\FileTraversing;
use Lochmueller\Index\Traversing\PageTraversing;
use Symfony\Component\Yaml\Yaml;
use TYPO3\CMS\Core\Site\Entity\SiteInterface;
use TYPO3\CMS\Extbase\Utility\DebuggerUtility;

class DatabaseIndexing implements IndexingInterface
{
    public function __construct(
        private PageTraversing $pageTraversing,
        private FileTraversing $fileTraversing,
    ) {}

    public function fillQueue(Configuration $configuration): void
    {

        #$indexConfiguration = Yaml::parse($site->getConfiguration()['sealIndexConfiguration'] ?? '');

        DebuggerUtility::var_dump($configuration);
        die();

        foreach ($this->loadTypes($site) as $type) {
            foreach ($type->getItems() as $item) {

            }

        }

        // Yaml::parse()


    }

    private function loadTypes(SiteInterface $site): iterable
    {

        foreach ($this->loadTypeConfiguration($site) as $configRecord) {

            // Handle type


            yield new Page($configRecord);

        }
    }

    private function loadTypeConfiguration(SiteInterface $site): array
    {

        // internal cache for all

        // get all for the specidic page


        return [];

    }

}
