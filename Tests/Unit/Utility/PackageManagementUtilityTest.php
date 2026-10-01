<?php

declare(strict_types=1);

namespace Lochmueller\Index\Tests\Unit\Utility;

use Lochmueller\Index\Tests\Unit\AbstractTest;
use Lochmueller\Index\Utility\PackageManagementUtility;
use TYPO3\CMS\ContentBlocks\Definition\ContentType\ContentType;
use TYPO3\CMS\ContentBlocks\Definition\ContentType\ContentTypeIcon;
use TYPO3\CMS\ContentBlocks\Definition\TableDefinitionCollection;
use TYPO3\CMS\ContentBlocks\Loader\LoadedContentBlock;
use TYPO3\CMS\ContentBlocks\Registry\AutomaticLanguageKeysRegistry;
use TYPO3\CMS\ContentBlocks\Registry\ContentBlockRegistry;
use TYPO3\CMS\ContentBlocks\Schema\SimpleTcaSchemaFactory;
use TYPO3\CMS\Core\Package\PackageManager;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class PackageManagementUtilityTest extends AbstractTest
{
    public function testContentBlockLookupsAreEmptyWhenContentBlocksIsNotActive(): void
    {
        $packageManager = $this->createMock(PackageManager::class);
        $packageManager->expects(self::exactly(2))
            ->method('isPackageActive')
            ->with('content_blocks')
            ->willReturn(false);

        $subject = new PackageManagementUtility($packageManager);

        // Second calls must be served from the cache, so the package manager is only asked twice.
        self::assertSame([], $subject->getContentBlockList());
        self::assertSame([], $subject->getContentBlockList());
        self::assertNull($subject->getContentBlockTableDefinitionCollection());
        self::assertNull($subject->getContentBlockTableDefinitionCollection());
    }

    public function testContentBlockLookupsUseContentBlocksServicesWhenActive(): void
    {
        $this->resetSingletonInstances = true;

        $packageManager = $this->createStub(PackageManager::class);
        $packageManager->method('isPackageActive')->willReturn(true);

        $simpleTcaSchemaFactory = $this->createStub(SimpleTcaSchemaFactory::class);
        $simpleTcaSchemaFactory->method('has')->willReturn(false);

        $contentElement = $this->createLoadedContentBlock(
            'vendor/block',
            ['table' => 'tt_content', 'typeName' => 'vendor_block', 'typeField' => 'CType'],
        );
        $registry = new ContentBlockRegistry($simpleTcaSchemaFactory);
        $registry->register($this->createLoadedContentBlock('vendor/without-type-name', ['table' => 'tt_content']));
        $registry->register($contentElement);
        $registry->register($this->createLoadedContentBlock(
            'vendor/record',
            ['table' => 'tx_vendor_record', 'typeName' => 'vendor_record', 'typeField' => 'type'],
            ContentType::RECORD_TYPE,
        ));
        GeneralUtility::addInstance(ContentBlockRegistry::class, $registry);

        $tableDefinitionCollection = new TableDefinitionCollection(new AutomaticLanguageKeysRegistry());
        GeneralUtility::addInstance(TableDefinitionCollection::class, $tableDefinitionCollection);

        $subject = new PackageManagementUtility($packageManager);

        // Second calls must be served from the cache, as no further instances are queued.
        self::assertSame(['vendor_block' => $contentElement], $subject->getContentBlockList());
        self::assertSame(['vendor_block' => $contentElement], $subject->getContentBlockList());
        self::assertSame($tableDefinitionCollection, $subject->getContentBlockTableDefinitionCollection());
        self::assertSame($tableDefinitionCollection, $subject->getContentBlockTableDefinitionCollection());
    }

    /**
     * @param array<string, mixed> $yaml
     */
    private function createLoadedContentBlock(
        string $name,
        array $yaml,
        ContentType $contentType = ContentType::CONTENT_ELEMENT,
    ): LoadedContentBlock {
        return new LoadedContentBlock(
            name: $name,
            yaml: $yaml,
            icon: ContentTypeIcon::fromArray([]),
            hostExtension: 'site_package',
            extPath: 'EXT:site_package/ContentBlocks/ContentElements/block',
            contentType: $contentType,
        );
    }
}
