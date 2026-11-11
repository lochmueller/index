<?php

declare(strict_types=1);

namespace Lochmueller\Index\Tests\Functional\Indexing\Record;

use Lochmueller\Index\Configuration\Configuration;
use Lochmueller\Index\Configuration\ConfigurationLoader;
use Lochmueller\Index\Enums\IndexPartialTrigger;
use Lochmueller\Index\Enums\IndexTechnology;
use Lochmueller\Index\Enums\IndexType;
use Lochmueller\Index\Indexing\Record\RecordIndexingQueue;
use Lochmueller\Index\Indexing\Record\RecordSourceResolver;
use Lochmueller\Index\Queue\Bus;
use Lochmueller\Index\Queue\Message\RecordIndexMessage;
use Lochmueller\Index\Traversing\RecordSelection;
use PHPUnit\Framework\Attributes\DataProvider;
use TYPO3\CMS\Core\Configuration\SiteWriter;
use TYPO3\CMS\Core\DataHandling\DataHandler;
use TYPO3\CMS\Core\Site\Entity\SiteInterface;
use TYPO3\CMS\Core\Site\SiteFinder;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use Symfony\Component\Messenger\Transport\Receiver\ListableReceiverInterface;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

final class RecordIndexingQueueFunctionalTest extends FunctionalTestCase
{
    protected array $coreExtensionsToLoad = [
        'typo3/cms-reactions',
        'typo3/cms-webhooks',
    ];

    protected array $testExtensionsToLoad = [
        'index',
    ];

    /**
     * @return array<string, array{IndexTechnology}>
     */
    public static function supportedTechnologies(): array
    {
        return [
            'database' => [IndexTechnology::Database],
            'frontend' => [IndexTechnology::Frontend],
            'http' => [IndexTechnology::Http],
        ];
    }

    #[DataProvider('supportedTechnologies')]
    public function testQueuesRecordsSelectedFromTcaStoragePages(IndexTechnology $technology): void
    {
        $connection = $this->getConnectionPool()->getConnectionForTable('tt_content');
        $connection->insert('tt_content', [
            'pid' => 41,
            'CType' => 'text',
            'header' => 'Functional record',
        ]);
        $recordUid = (int) $connection->lastInsertId('tt_content');

        $site = $this->createStub(SiteInterface::class);
        $site->method('getIdentifier')->willReturn('main');
        $configuration = new Configuration(
            configurationId: 7,
            pageId: 1,
            technology: $technology,
            skipNoSearchPages: false,
            contentIndexing: true,
            levels: 0,
            fileMounts: [],
            fileTypes: [],
            configuration: [
                'records' => [[
                    'table' => 'tt_content',
                    'recordStorages' => [41],
                    'fields' => ['title' => 'header'],
                ]],
            ],
            partialIndexing: [],
            languages: [],
        );

        $messages = [];
        $bus = $this->createMock(Bus::class);
        $bus->expects(self::once())
            ->method('dispatch')
            ->willReturnCallback(static function (object $message) use (&$messages): void {
                $messages[] = $message;
            });

        $recordIndexingQueue = new RecordIndexingQueue(
            $bus,
            $this->createStub(ConfigurationLoader::class),
            $this->get(RecordSelection::class),
            new RecordSourceResolver(),
            $this->createStub(SiteFinder::class),
        );
        $recordIndexingQueue->queueConfiguredRecords($configuration, $site, 'process-1', IndexType::Full);

        self::assertCount(1, $messages);
        self::assertInstanceOf(RecordIndexMessage::class, $messages[0]);
        self::assertSame($recordUid, $messages[0]->recordUid);
        self::assertSame($technology, $messages[0]->technology);
        self::assertSame(0, $messages[0]->language);
    }

    public function testDataHandlerMoveQueuesRecordForItsPreviousConfiguration(): void
    {
        $connectionPool = $this->getConnectionPool();
        $pagesConnection = $connectionPool->getConnectionForTable('pages');
        $pagesConnection->insert('pages', [
            'pid' => 0,
            'title' => 'Root',
            'doktype' => 1,
            'slug' => '/',
            'is_siteroot' => 1,
            'hidden' => 0,
            'sys_language_uid' => 0,
        ]);
        $rootPageId = (int) $pagesConnection->lastInsertId();
        $pagesConnection->insert('pages', [
            'pid' => $rootPageId,
            'title' => 'Record storage',
            'doktype' => 254,
            'slug' => '/records',
            'hidden' => 0,
            'sys_language_uid' => 0,
        ]);
        $storagePageId = (int) $pagesConnection->lastInsertId();
        $pagesConnection->insert('pages', [
            'pid' => $rootPageId,
            'title' => 'Other storage',
            'doktype' => 254,
            'slug' => '/other',
            'hidden' => 0,
            'sys_language_uid' => 0,
        ]);
        $otherPageId = (int) $pagesConnection->lastInsertId();
        $this->get(SiteWriter::class)->createNewBasicSite('main', $rootPageId, '/');

        $configurationConnection = $connectionPool->getConnectionForTable('tx_index_domain_model_configuration');
        $configurationConnection->insert('tx_index_domain_model_configuration', [
            'pid' => $rootPageId,
            'title' => 'Record index',
            'technology' => IndexTechnology::Http->value,
            'configuration' => [
                'records' => [[
                    'table' => 'tt_content',
                    'recordStorages' => [$storagePageId],
                    'fields' => ['title' => 'header'],
                ]],
            ],
            'partial_indexing' => IndexPartialTrigger::Cmdmap->value,
            'content_indexing' => 1,
            'skip_no_search_pages' => 0,
            'levels' => 0,
            'hidden' => 0,
            'deleted' => 0,
        ]);

        $contentConnection = $connectionPool->getConnectionForTable('tt_content');
        $contentConnection->insert('tt_content', [
            'pid' => $storagePageId,
            'CType' => 'text',
            'header' => 'Record title',
            'hidden' => 0,
            'sys_language_uid' => 0,
        ]);
        $recordUid = (int) $contentConnection->lastInsertId();

        $backendUserConnection = $connectionPool->getConnectionForTable('be_users');
        $backendUserConnection->insert('be_users', [
            'pid' => 0,
            'username' => 'functional-index-admin',
            'admin' => 1,
            'disable' => 0,
            'deleted' => 0,
        ]);
        $backendUser = $this->setUpBackendUser((int) $backendUserConnection->lastInsertId());

        $configurationLoader = $this->get(ConfigurationLoader::class);
        $siteFinder = $this->get(SiteFinder::class);
        $site = $siteFinder->getSiteByPageId($storagePageId);
        self::assertSame('main', $site->getIdentifier());
        $configurations = iterator_to_array($configurationLoader->loadAllBySite($site));
        self::assertCount(1, $configurations);
        self::assertSame([IndexPartialTrigger::Cmdmap->value], $configurations[0]->partialIndexing);
        self::assertSame(['records' => [[
            'table' => 'tt_content',
            'recordStorages' => [$storagePageId],
            'fields' => ['title' => 'header'],
        ]]], $configurations[0]->configuration);
        self::assertNotNull((new RecordSourceResolver())->findForRecord(
            $configurations[0],
            'tt_content',
            $storagePageId,
        ));
        self::assertSame(
            \Lochmueller\Index\Hooks\DataHandlerUpdateHook::class,
            $GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['t3lib/class.t3lib_tcemain.php']['processCmdmapClass']['ext:index'] ?? null,
        );

        $dataHandler = GeneralUtility::makeInstance(DataHandler::class);
        $dataHandler->start([], [
            'tt_content' => [
                $recordUid => ['move' => $otherPageId],
            ],
        ], $backendUser);
        $dataHandler->process_cmdmap();
        self::assertSame([], $dataHandler->errorLog);

        self::assertSame(
            $otherPageId,
            (int) $contentConnection->select(['pid'], 'tt_content', ['uid' => $recordUid])->fetchOne(),
        );

        $transport = $this->get('messenger.transport.index');
        self::assertInstanceOf(ListableReceiverInterface::class, $transport);
        $recordMessages = [];
        foreach ($transport->all() as $envelope) {
            $message = $envelope->getMessage();
            if ($message instanceof RecordIndexMessage) {
                $recordMessages[] = $message;
            }
        }

        $matchingMessages = array_filter(
            $recordMessages,
            static fn(RecordIndexMessage $message): bool => $message->recordUid === $recordUid
                && $message->indexConfigurationRecordId === $configurations[0]->configurationId,
        );
        $matchingMessages = array_values($matchingMessages);
        self::assertCount(1, $matchingMessages);
        self::assertSame(IndexTechnology::Http, $matchingMessages[0]->technology);
        self::assertSame(IndexType::Partial, $matchingMessages[0]->type);
    }
}
