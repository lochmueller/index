<?php

declare(strict_types=1);

namespace Lochmueller\Index\Tests\Unit\Indexing\Database\ContentType\BootstrapPackage;

use Lochmueller\Index\Indexing\Database\ContentType\BootstrapPackage\AbstractBootstrapPackageContentType;
use Lochmueller\Index\Indexing\Database\ContentType\BootstrapPackage\BootstrapPackageSimpleContentType;
use Lochmueller\Index\Indexing\Database\ContentType\HeaderContentType;
use Lochmueller\Index\Indexing\Database\DatabaseIndexingDto;
use Lochmueller\Index\Tests\Unit\AbstractTest;
use PHPUnit\Framework\Attributes\DataProvider;
use TYPO3\CMS\Core\Domain\Record;
use TYPO3\CMS\Core\Package\PackageManager;
use TYPO3\CMS\Core\Site\Entity\Site;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class BootstrapPackageSimpleContentTypeTest extends AbstractTest
{
    protected function setUp(): void
    {
        parent::setUp();
        AbstractBootstrapPackageContentType::resetBootstrapPackageActiveCache();
    }

    protected function tearDown(): void
    {
        AbstractBootstrapPackageContentType::resetBootstrapPackageActiveCache();
        GeneralUtility::purgeInstances();
        parent::tearDown();
    }

    private function setBootstrapPackageActive(bool $active): void
    {
        $packageManager = $this->createStub(PackageManager::class);
        $packageManager->method('isPackageActive')->with('bootstrap_package')->willReturn($active);
        GeneralUtility::setSingletonInstance(PackageManager::class, $packageManager);
    }

    private function createRecordWithType(string $type): Record
    {
        $record = $this->createStub(Record::class);
        $record->method('getRecordType')->willReturn($type);
        return $record;
    }

    /**
     * @return array<string, array{string}>
     */
    public static function supportedTypesProvider(): array
    {
        return [
            'textcolumn' => ['textcolumn'],
            'texticon' => ['texticon'],
            'listgroup' => ['listgroup'],
            'panel' => ['panel'],
            'textteaser' => ['textteaser'],
            'quote' => ['quote'],
        ];
    }

    /**
     * Property 10: canHandle returns true for supported types when installed
     * Validates: Requirements 8.2
     */
    #[DataProvider('supportedTypesProvider')]
    public function testCanHandleReturnsTrueForSupportedTypesWhenPackageActive(string $type): void
    {
        $this->setBootstrapPackageActive(true);

        $record = $this->createRecordWithType($type);
        $headerContentType = $this->createStub(HeaderContentType::class);
        $subject = new BootstrapPackageSimpleContentType($headerContentType);

        self::assertTrue($subject->canHandle($record));
    }

    /**
     * @return array<string, array{string}>
     */
    public static function unsupportedTypesProvider(): array
    {
        return [
            'accordion' => ['accordion'],
            'tab' => ['tab'],
            'card_group' => ['card_group'],
            'carousel' => ['carousel'],
            'text' => ['text'],
            'header' => ['header'],
            'image' => ['image'],
        ];
    }

    #[DataProvider('unsupportedTypesProvider')]
    public function testCanHandleReturnsFalseForUnsupportedTypes(string $type): void
    {
        $this->setBootstrapPackageActive(true);

        $record = $this->createRecordWithType($type);
        $headerContentType = $this->createStub(HeaderContentType::class);
        $subject = new BootstrapPackageSimpleContentType($headerContentType);

        self::assertFalse($subject->canHandle($record));
    }

    #[DataProvider('supportedTypesProvider')]
    public function testCanHandleReturnsFalseWhenPackageNotActive(string $type): void
    {
        $this->setBootstrapPackageActive(false);

        $record = $this->createRecordWithType($type);
        $headerContentType = $this->createStub(HeaderContentType::class);
        $subject = new BootstrapPackageSimpleContentType($headerContentType);

        self::assertFalse($subject->canHandle($record));
    }

    /**
     * @param array<string, mixed> $fields
     */
    private function createRecordWithFields(string $type, array $fields): Record
    {
        $record = $this->createStub(Record::class);
        $record->method('getRecordType')->willReturn($type);
        $record->method('get')->willReturnCallback(fn(string $field) => $fields[$field] ?? null);
        return $record;
    }

    private function createDto(): DatabaseIndexingDto
    {
        $site = $this->createStub(Site::class);
        return new DatabaseIndexingDto('', '', 1, 0, [], $site);
    }

    /**
     * Generates random string data for property testing.
     *
     * @return \Generator<string, array{string, string}>
     */
    public static function simpleTypesWithContentProvider(): \Generator
    {
        srand(42);
        $simpleTypes = ['textcolumn', 'texticon', 'listgroup', 'panel'];

        for ($i = 0; $i < self::TEST_ITERATIONS; $i++) {
            $type = $simpleTypes[array_rand($simpleTypes)];
            $bodytext = 'Body content ' . random_int(1, 10000);
            yield "iteration_{$i}_{$type}" => [$type, $bodytext];
        }
    }

    /**
     * Property 1: Simple types extract header and bodytext
     * For any simple Bootstrap Package content element (textcolumn, texticon, listgroup, panel)
     * with non-empty bodytext field, the indexed content SHALL contain the bodytext.
     *
     * Validates: Requirements 1.1, 1.4, 1.5, 1.6
     */
    #[DataProvider('simpleTypesWithContentProvider')]
    public function testSimpleTypesExtractBodytext(string $type, string $bodytext): void
    {
        $this->setBootstrapPackageActive(true);

        $record = $this->createRecordWithFields($type, ['bodytext' => $bodytext]);
        $dto = $this->createDto();

        $headerContentType = $this->createStub(HeaderContentType::class);
        $subject = new BootstrapPackageSimpleContentType($headerContentType);
        $subject->addContent($record, $dto);

        self::assertStringContainsString(
            $bodytext,
            $dto->content,
            sprintf('Bodytext "%s" not found in content for type "%s"', $bodytext, $type),
        );
    }

    /**
     * Generates random textteaser data for property testing.
     *
     * @return \Generator<string, array{string, string, string}>
     */
    public static function textteaserContentProvider(): \Generator
    {
        srand(43);

        for ($i = 0; $i < self::TEST_ITERATIONS; $i++) {
            $teaser = 'Teaser text ' . random_int(1, 10000);
            $bodytext = 'Body content ' . random_int(1, 10000);
            yield "iteration_{$i}" => [$teaser, $bodytext];
        }
    }

    /**
     * Property 2: Textteaser extracts all text fields
     * For any textteaser content element with non-empty teaser and bodytext fields,
     * the indexed content SHALL contain both field values.
     *
     * Validates: Requirements 1.2
     */
    #[DataProvider('textteaserContentProvider')]
    public function testTextteaserExtractsAllTextFields(string $teaser, string $bodytext): void
    {
        $this->setBootstrapPackageActive(true);

        $record = $this->createRecordWithFields('textteaser', [
            'teaser' => $teaser,
            'bodytext' => $bodytext,
        ]);
        $dto = $this->createDto();

        $headerContentType = $this->createStub(HeaderContentType::class);
        $subject = new BootstrapPackageSimpleContentType($headerContentType);
        $subject->addContent($record, $dto);

        self::assertStringContainsString($teaser, $dto->content, 'Teaser not found in content');
        self::assertStringContainsString($bodytext, $dto->content, 'Bodytext not found in content');
    }

    /**
     * Generates random quote data for property testing.
     *
     * @return \Generator<string, array{string, string}>
     */
    public static function quoteContentProvider(): \Generator
    {
        srand(44);

        for ($i = 0; $i < self::TEST_ITERATIONS; $i++) {
            $quoteSource = 'Quote source ' . random_int(1, 10000);
            $bodytext = 'Quote body ' . random_int(1, 10000);
            yield "iteration_{$i}" => [$quoteSource, $bodytext];
        }
    }

    /**
     * Property 3: Quote extracts all text fields including source
     * For any quote content element with non-empty bodytext and quote_source fields,
     * the indexed content SHALL contain both field values.
     *
     * Validates: Requirements 1.3
     */
    #[DataProvider('quoteContentProvider')]
    public function testQuoteExtractsAllTextFieldsIncludingSource(string $quoteSource, string $bodytext): void
    {
        $this->setBootstrapPackageActive(true);

        $record = $this->createRecordWithFields('quote', [
            'quote_source' => $quoteSource,
            'bodytext' => $bodytext,
        ]);
        $dto = $this->createDto();

        $headerContentType = $this->createStub(HeaderContentType::class);
        $subject = new BootstrapPackageSimpleContentType($headerContentType);
        $subject->addContent($record, $dto);

        self::assertStringContainsString($quoteSource, $dto->content, 'Quote source not found in content');
        self::assertStringContainsString($bodytext, $dto->content, 'Bodytext not found in content');
    }

    /**
     * @return array<string, array{string}>
     */
    public static function allSimpleTypesProvider(): array
    {
        return [
            'textcolumn' => ['textcolumn'],
            'texticon' => ['texticon'],
            'listgroup' => ['listgroup'],
            'panel' => ['panel'],
            'textteaser' => ['textteaser'],
            'quote' => ['quote'],
        ];
    }

    /**
     * Test empty fields are handled gracefully
     * Validates: Requirements 10.1
     */
    #[DataProvider('allSimpleTypesProvider')]
    public function testEmptyFieldsAreHandledGracefully(string $type): void
    {
        $this->setBootstrapPackageActive(true);

        $record = $this->createRecordWithFields($type, [
            'bodytext' => '',
            'teaser' => '',
            'quote_source' => '',
        ]);
        $dto = $this->createDto();

        $headerContentType = $this->createStub(HeaderContentType::class);
        $subject = new BootstrapPackageSimpleContentType($headerContentType);

        // Should not throw any exception
        $subject->addContent($record, $dto);

        // Content should be empty (only header would be added by HeaderContentType)
        self::assertSame('', $dto->content);
    }

    /**
     * Test that HeaderContentType is called for all types
     */
    #[DataProvider('allSimpleTypesProvider')]
    public function testHeaderContentTypeIsCalledForAllTypes(string $type): void
    {
        $this->setBootstrapPackageActive(true);

        $record = $this->createRecordWithFields($type, ['bodytext' => 'test']);
        $dto = $this->createDto();

        $headerContentType = $this->createMock(HeaderContentType::class);
        $headerContentType->expects(self::once())->method('addContent')->with($record, $dto);

        $subject = new BootstrapPackageSimpleContentType($headerContentType);
        $subject->addContent($record, $dto);
    }
}
