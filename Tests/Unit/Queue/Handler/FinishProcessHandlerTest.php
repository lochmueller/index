<?php

declare(strict_types=1);

namespace Lochmueller\Index\Tests\Unit\Queue\Handler;

use Lochmueller\Index\Enums\IndexTechnology;
use Lochmueller\Index\Enums\IndexType;
use Lochmueller\Index\Queue\Handler\FinishProcessHandler;
use Lochmueller\Index\Queue\Message\FinishProcessMessage;
use Lochmueller\Index\Tests\Unit\AbstractTest;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;
use TYPO3\CMS\Core\Exception\SiteNotFoundException;
use TYPO3\CMS\Core\Site\SiteFinder;

class FinishProcessHandlerTest extends AbstractTest
{
    public function testExceptionIsLoggedWhenSiteFinderThrows(): void
    {
        $exception = new SiteNotFoundException('Site "unknown" not found', 1234567890);

        $siteFinderStub = $this->createStub(SiteFinder::class);
        $siteFinderStub->method('getSiteByIdentifier')->willThrowException($exception);

        $eventDispatcherStub = $this->createStub(EventDispatcherInterface::class);

        $loggerMock = $this->createMock(LoggerInterface::class);
        $loggerMock->expects(self::once())
            ->method('error')
            ->with($exception->getMessage(), ['exception' => $exception]);

        $subject = new FinishProcessHandler($eventDispatcherStub, $siteFinderStub);
        $subject->setLogger($loggerMock);

        $message = new FinishProcessMessage(
            siteIdentifier: 'unknown',
            technology: IndexTechnology::Database,
            type: IndexType::Full,
            indexConfigurationRecordId: null,
            indexProcessId: 'test-process-id',
        );

        $subject($message);
    }
}
