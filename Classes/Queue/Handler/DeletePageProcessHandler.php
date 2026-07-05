<?php

declare(strict_types=1);

namespace Lochmueller\Index\Queue\Handler;

use Lochmueller\Index\Event\DeletePageEvent;
use Lochmueller\Index\Queue\Message\DeletePageMessage;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use TYPO3\CMS\Core\Exception\SiteNotFoundException;
use TYPO3\CMS\Core\Routing\InvalidRouteArgumentsException;
use TYPO3\CMS\Core\Site\SiteFinder;

final class DeletePageProcessHandler implements LoggerAwareInterface
{
    use LoggerAwareTrait;

    public function __construct(
        protected SiteFinder $siteFinder,
        private readonly EventDispatcherInterface $eventDispatcher,
    )
    {
    }

    #[AsMessageHandler]
    public function __invoke(DeletePageMessage $message): void
    {
        try {
            $site = $this->siteFinder->getSiteByPageId($message->pageUid);
            $language = $site->getLanguageById($message->languageId);

            $uri = (string)$site->getRouter()->generateUri($message->pageUid, ['_language' => $language]);

            $this->eventDispatcher->dispatch(new DeletePageEvent($site, $uri));
        } catch (SiteNotFoundException|\InvalidArgumentException|InvalidRouteArgumentsException $exception) {
            $this->logger?->error($exception->getMessage(), ['exception' => $exception]);
        } catch (\Exception $exception) {
            $d=1;
        }
    }
}
