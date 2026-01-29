<?php

declare(strict_types=1);

namespace Lochmueller\Index\Tests\Unit\Traversing;

use Lochmueller\Index\Tests\Unit\AbstractTest;
use Lochmueller\Index\Traversing\FileTraversing;
use TYPO3\CMS\Core\Resource\ResourceFactory;

class FileTraversingTest extends AbstractTest
{
    public function testClassCanBeInstantiated(): void
    {
        $resourceFactory = $this->createStub(ResourceFactory::class);
        $subject = new FileTraversing($resourceFactory);

        self::assertInstanceOf(FileTraversing::class, $subject);
    }
}
