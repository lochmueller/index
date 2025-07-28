<?php

declare(strict_types=1);

namespace Lochmueller\Indexing\Indexing\Database;

use Lochmueller\Indexing\Indexing\Database\Types\Page;
use Lochmueller\Indexing\Indexing\IndexingInterface;
use Symfony\Component\Yaml\Yaml;
use TYPO3\CMS\Core\Site\Entity\SiteInterface;
use TYPO3\CMS\Extbase\Utility\DebuggerUtility;

class DatabaseIndexing implements IndexingInterface
{
    public function __construct(
        protected PageTraversing $pageTraversing,
    ) {}

    public function indexDatabase(SiteInterface $site): void
    {

        $indexConfiguration = Yaml::parse($site->getConfiguration()['sealIndexConfiguration'] ?? '');

        DebuggerUtility::var_dump($indexConfiguration);
        die();


        foreach ($this->loadTypes($site) as $type) {
            foreach ($type->getItems() as $item) {

            }

        }

        // Yaml::parse()


    }

    protected function loadTypes(SiteInterface $site): iterable
    {

        foreach ($this->loadTypeConfiguration($site) as $configRecord) {

            // Handle type


            yield new Page($configRecord);

        }
    }

    protected function loadTypeConfiguration(SiteInterface $site): array
    {

        // internal cache for all

        // get all for the specidic page


        return [];

    }

}
