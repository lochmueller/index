<?php

declare(strict_types=1);

namespace Lochmueller\Index\Tests\Unit\Event;

use Lochmueller\Index\Event\DeIndexDocumentEvent;
use Lochmueller\Index\Tests\Unit\AbstractTest;
use TYPO3\CMS\Core\Site\Entity\SiteInterface;

class DeIndexDocumentEventTest extends AbstractTest
{
    public function testConstructorSetsAllProperties(): void
    {
        $site = $this->createStub(SiteInterface::class);

        $subject = new DeIndexDocumentEvent(
            site: $site,
            uri: 'https://example.com/deleted-page',
        );

        self::assertSame($site, $subject->site);
        self::assertSame('https://example.com/deleted-page', $subject->uri);
    }

    public function testEventIsReadonly(): void
    {
        $reflection = new \ReflectionClass(DeIndexDocumentEvent::class);

        self::assertTrue($reflection->isReadOnly());
    }

    public function testEmptyUriIsHandled(): void
    {
        $subject = new DeIndexDocumentEvent(
            site: $this->createStub(SiteInterface::class),
            uri: '',
        );

        self::assertSame('', $subject->uri);
    }
}
