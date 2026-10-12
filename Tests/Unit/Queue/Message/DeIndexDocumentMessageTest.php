<?php

declare(strict_types=1);

namespace Lochmueller\Index\Tests\Unit\Queue\Message;

use Lochmueller\Index\Queue\Message\DeIndexDocumentMessage;
use Lochmueller\Index\Tests\Unit\AbstractTest;

class DeIndexDocumentMessageTest extends AbstractTest
{
    public function testConstructorSetsProperties(): void
    {
        $message = new DeIndexDocumentMessage(42, 1, 'https://example.com/en/page', 'main');

        self::assertSame(42, $message->pageUid);
        self::assertSame(1, $message->languageId);
        self::assertSame('https://example.com/en/page', $message->uri);
        self::assertSame('main', $message->siteIdentifier);
    }

    public function testUnserializeOfMessageWithoutUriUsesDefaults(): void
    {
        // Serialized message of the version without uri and site identifier (still in the queue on update)
        $serialized = 'O:' . strlen(DeIndexDocumentMessage::class) . ':"' . DeIndexDocumentMessage::class . '":2:{s:7:"pageUid";i:42;s:10:"languageId";i:1;}';

        $message = unserialize($serialized);

        self::assertInstanceOf(DeIndexDocumentMessage::class, $message);
        self::assertSame(42, $message->pageUid);
        self::assertSame('', $message->uri);
        self::assertSame('', $message->siteIdentifier);
    }
}
