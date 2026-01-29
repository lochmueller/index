<?php

declare(strict_types=1);

namespace Lochmueller\Index\Tests\Unit\EventListener;

use Lochmueller\Index\EventListener\AfterCacheableContentIsGeneratedEventListener;
use Lochmueller\Index\Indexing\Cache\CacheIndexingQueue;
use Lochmueller\Index\Tests\Unit\AbstractTest;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Frontend\Controller\TypoScriptFrontendController;
use TYPO3\CMS\Frontend\Event\AfterCacheableContentIsGeneratedEvent;

class AfterCacheableContentIsGeneratedEventListenerTest extends AbstractTest
{
    public function testInvokeCallsFillQueueOnCacheIndexingQueue(): void
    {
        $requestStub = $this->createStub(ServerRequestInterface::class);

        if (class_exists(TypoScriptFrontendController::class)) {
            $secondParameter = $this->createStub(TypoScriptFrontendController::class);
        } else {
            $secondParameter = '<html>Test content</html>';
        }

        $event = new AfterCacheableContentIsGeneratedEvent(
            $requestStub,
            $secondParameter,
            'cache-identifier',
            true,
        );

        $cacheIndexingQueueMock = $this->createMock(CacheIndexingQueue::class);
        $cacheIndexingQueueMock->expects(self::once())
            ->method('fillQueue')
            ->with($event);

        $subject = new AfterCacheableContentIsGeneratedEventListener($cacheIndexingQueueMock);
        $subject($event);
    }
}
