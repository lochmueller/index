<?php

declare(strict_types=1);

namespace Lochmueller\Index\Indexing\Frontend;

use Psr\Http\Message\UriInterface;
use Psr\Log\LoggerInterface;
use TYPO3\CMS\Core\Http\NormalizedParams;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Frontend\Http\Application;

/**
 * @see https://github.com/b13/warmup/blob/main/Classes/FrontendRequestBuilder.php
 * @see https://github.com/georgringer/audit/blob/main/Classes/FrontendRequestBuilder.php
 */
class FrontendRequestBuilder
{
    public function __construct(protected Application $application, protected LoggerInterface $logger) {}

    public function buildRequestForPage(UriInterface $uri, $frontendUserGroups = []): ?string
    {
        $serverParams = [
            'SCRIPT_NAME' => '/index.php',
            'HTTP_HOST'  => $uri->getHost(),
            'SERVER_NAME' => $uri->getHost(),
            'HTTPS' => $uri->getScheme() === 'https' ? 'on' : 'off',
            'REMOTE_ADDR' => '127.0.0.1',
        ];
        $headers = [];
        $serverRequest = new ServerRequest($uri, 'GET', null, $headers, $serverParams);
        $serverRequest = $serverRequest->withAttribute('normalizedParams', NormalizedParams::createFromRequest($serverRequest));

        try {
            $response = $this->application->handle($serverRequest);
            return $response->getBody()->getContents();
        } catch (\Exception $e) {
            $this->logger->error('cannot fetch url ' . (string) $uri);
            return null;
        }
    }
}
