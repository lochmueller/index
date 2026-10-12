<?php

declare(strict_types=1);

namespace Lochmueller\Index\Queue\Message;

final class DeIndexDocumentMessage
{
    /**
     * The URI is resolved when the message is created, if possible. A deleted, moved or hidden
     * page has no (or another) URI when the message is handled asynchronously.
     *
     * No constructor promotion: messages that are serialized in the queue before these properties
     * existed get the default values on unserialize.
     */
    public string $uri = '';

    public string $siteIdentifier = '';

    public function __construct(
        public int $pageUid,
        public int $languageId,
        string $uri = '',
        string $siteIdentifier = '',
    ) {
        $this->uri = $uri;
        $this->siteIdentifier = $siteIdentifier;
    }

}
