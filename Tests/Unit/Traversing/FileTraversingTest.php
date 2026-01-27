<?php

declare(strict_types=1);

namespace Lochmueller\Index\Tests\Unit\Traversing;

use Lochmueller\Index\Tests\Unit\AbstractTest;
use Lochmueller\Index\Traversing\FileTraversing;
use TYPO3\CMS\Core\Resource\ResourceFactory;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class FileTraversingTest extends AbstractTest
{
    protected function setUp(): void
    {
        parent::setUp();
        GeneralUtility::setSingletonInstance(ResourceFactory::class, $this->createStub(ResourceFactory::class));
    }

    protected function tearDown(): void
    {
        GeneralUtility::removeSingletonInstance(ResourceFactory::class, GeneralUtility::makeInstance(ResourceFactory::class));
        parent::tearDown();
    }

    public function testClassCanBeInstantiated(): void
    {
        $subject = new FileTraversing();

        self::assertInstanceOf(FileTraversing::class, $subject);
    }
}
