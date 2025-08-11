<?php

declare(strict_types=1);

namespace Lochmueller\Index\Indexing\Frontend;

use Lochmueller\Index\Indexing\IndexingInterface;
use Lochmueller\Index\Queue\Message\FrontendIndexMessage;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use TYPO3\CMS\Core\Http\Uri;
use TYPO3\CMS\Core\Site\SiteFinder;

readonly class FrontendIndexingHandler implements IndexingInterface
{
    public function __construct(
        private SiteFinder          $siteFinder,
        private FrontendRequestBuilder $frontendRequestBuilder,
    ) {}
    #[AsMessageHandler]

    public function __invoke(FrontendIndexMessage $message): void
    {

        //

        #$uri = new Uri('https://htd-distribution.lndo.site/de/team/');

        #$result = $this->frontendRequestBuilder->buildRequestForPage($uri);

        // @todo handle message
        // @todo Execute webrequest and index content
    }

}
