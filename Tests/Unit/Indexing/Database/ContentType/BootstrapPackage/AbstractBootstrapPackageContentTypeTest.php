<?php

declare(strict_types=1);

namespace Lochmueller\Index\Tests\Unit\Indexing\Database\ContentType\BootstrapPackage;

use Lochmueller\Index\Indexing\Database\ContentType\BootstrapPackage\AbstractBootstrapPackageContentType;
use Lochmueller\Index\Indexing\Database\ContentType\HeaderContentType;
use Lochmueller\Index\Indexing\Database\DatabaseIndexingDto;
use Lochmueller\Index\Tests\Unit\AbstractTest;
use TYPO3\CMS\Core\Domain\Record;
use TYPO3\CMS\Core\Package\PackageManager;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class AbstractBootstrapPackageContentTypeTest extends AbstractTest
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

    public function testIsBootstrapPackageActiveReturnsFalseWhenPackageNotInstalled(): void
    {
        $packageManager = $this->createStub(PackageManager::class);
        $packageManager->method('isPackageActive')->willReturn(false);
        GeneralUtility::setSingletonInstance(PackageManager::class, $packageManager);

        $headerContentType = $this->createStub(HeaderContentType::class);
        $subject = new ConcreteBootstrapPackageContentType($headerContentType);

        self::assertFalse($subject->exposedIsBootstrapPackageActive());
    }

    public function testIsBootstrapPackageActiveReturnsTrueWhenPackageInstalled(): void
    {
        $packageManager = $this->createStub(PackageManager::class);
        $packageManager->method('isPackageActive')->willReturn(true);
        GeneralUtility::setSingletonInstance(PackageManager::class, $packageManager);

        $headerContentType = $this->createStub(HeaderContentType::class);
        $subject = new ConcreteBootstrapPackageContentType($headerContentType);

        self::assertTrue($subject->exposedIsBootstrapPackageActive());
    }

    public function testIsBootstrapPackageActiveUsesStaticCache(): void
    {
        $packageManager = $this->createMock(PackageManager::class);
        $packageManager->expects(self::once())
            ->method('isPackageActive')
            ->willReturn(true);
        GeneralUtility::setSingletonInstance(PackageManager::class, $packageManager);

        $headerContentType = $this->createStub(HeaderContentType::class);
        $subject = new ConcreteBootstrapPackageContentType($headerContentType);

        // Call twice - should only hit PackageManager once due to static caching
        $subject->exposedIsBootstrapPackageActive();
        $subject->exposedIsBootstrapPackageActive();
    }

    public function testIsTypeReturnsTrueForMatchingType(): void
    {
        $record = $this->createStub(Record::class);
        $record->method('getRecordType')->willReturn('accordion');

        $headerContentType = $this->createStub(HeaderContentType::class);
        $subject = new ConcreteBootstrapPackageContentType($headerContentType);

        self::assertTrue($subject->exposedIsType($record, 'accordion'));
    }

    public function testIsTypeReturnsFalseForNonMatchingType(): void
    {
        $record = $this->createStub(Record::class);
        $record->method('getRecordType')->willReturn('accordion');

        $headerContentType = $this->createStub(HeaderContentType::class);
        $subject = new ConcreteBootstrapPackageContentType($headerContentType);

        self::assertFalse($subject->exposedIsType($record, 'tab'));
    }

    public function testIsAnyTypeReturnsTrueWhenTypeInArray(): void
    {
        $record = $this->createStub(Record::class);
        $record->method('getRecordType')->willReturn('accordion');

        $headerContentType = $this->createStub(HeaderContentType::class);
        $subject = new ConcreteBootstrapPackageContentType($headerContentType);

        self::assertTrue($subject->exposedIsAnyType($record, ['accordion', 'tab', 'carousel']));
    }

    public function testIsAnyTypeReturnsFalseWhenTypeNotInArray(): void
    {
        $record = $this->createStub(Record::class);
        $record->method('getRecordType')->willReturn('textcolumn');

        $headerContentType = $this->createStub(HeaderContentType::class);
        $subject = new ConcreteBootstrapPackageContentType($headerContentType);

        self::assertFalse($subject->exposedIsAnyType($record, ['accordion', 'tab', 'carousel']));
    }

    /**
     * Property 9: canHandle returns false when Bootstrap Package not installed
     */
    public function testCanHandleReturnsFalseWhenBootstrapPackageNotInstalled(): void
    {
        $packageManager = $this->createStub(PackageManager::class);
        $packageManager->method('isPackageActive')->willReturn(false);
        GeneralUtility::setSingletonInstance(PackageManager::class, $packageManager);

        $record = $this->createStub(Record::class);
        $record->method('getRecordType')->willReturn('accordion');

        $headerContentType = $this->createStub(HeaderContentType::class);
        $subject = new ConcreteBootstrapPackageContentType($headerContentType);

        self::assertFalse($subject->canHandle($record));
    }
}

/**
 * Concrete implementation for testing the abstract class.
 */
class ConcreteBootstrapPackageContentType extends AbstractBootstrapPackageContentType
{
    public function canHandle(Record $record): bool
    {
        if (!$this->isBootstrapPackageActive()) {
            return false;
        }
        return $this->isType($record, 'accordion');
    }

    public function addContent(Record $record, DatabaseIndexingDto $dto): void
    {
        // Not needed for these tests
    }

    public function exposedIsBootstrapPackageActive(): bool
    {
        return $this->isBootstrapPackageActive();
    }

    public function exposedIsType(Record $record, string $type): bool
    {
        return $this->isType($record, $type);
    }

    /**
     * @param array<string> $types
     */
    public function exposedIsAnyType(Record $record, array $types): bool
    {
        return $this->isAnyType($record, $types);
    }
}
