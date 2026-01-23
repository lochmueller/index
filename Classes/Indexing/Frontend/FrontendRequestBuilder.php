<?php

declare(strict_types=1);

namespace Lochmueller\Index\Indexing\Frontend;

use Psr\Http\Message\UriInterface;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use TYPO3\CMS\Core\Core\SystemEnvironmentBuilder;
use TYPO3\CMS\Core\Http\MiddlewareDispatcher;
use TYPO3\CMS\Core\Http\NormalizedParams;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Frontend\Http\Application;
use TYPO3\CMS\Frontend\Http\RequestHandler;

/**
 * @see https://github.com/b13/warmup/blob/main/Classes/FrontendRequestBuilder.php
 * @see https://github.com/georgringer/audit/blob/main/Classes/FrontendRequestBuilder.php
 */
class FrontendRequestBuilder implements LoggerAwareInterface
{
    use LoggerAwareTrait;


    public function __construct(
        protected Application            $application,
        protected FrontendContextBuilder $frontendContextBuilder,
    ) {}

    public function buildRequestForPage(UriInterface $uri): ?string
    {
        $serverParams = [
            'SCRIPT_NAME' => '/index.php',
            'HTTP_HOST' => $uri->getHost(),
            'SERVER_NAME' => $uri->getHost(),
            'HTTPS' => $uri->getScheme() === 'https' ? 'on' : 'off',
            'REMOTE_ADDR' => '127.0.0.1',
            'CONTEXT_DOCUMENT_ROOT' => '/app/public',
            'REQUEST_URI' => parse_url((string) $uri, PHP_URL_PATH),
        ];

        $headers = [];
        $serverRequest = new ServerRequest($uri, 'GET', null, $headers, $serverParams);
        if ($query = parse_url((string) $uri, PHP_URL_QUERY)) {
            parse_str($query, $queryResult);
            $serverRequest = $serverRequest->withQueryParams($queryResult);
        }
        $serverRequest = $serverRequest->withAttribute('normalizedParams', NormalizedParams::createFromRequest($serverRequest));
        $serverRequest = $serverRequest->withAttribute('applicationType', SystemEnvironmentBuilder::REQUESTTYPE_FE);

        try {
            $response = $this->frontendContextBuilder->executeInFrontendContext(function () use ($serverRequest) {
                $dispatcher = $this->buildDispatcher();
                return $dispatcher->handle($serverRequest);
            });

            $response->getBody()->rewind();
            return $response->getBody()->getContents();
        } catch (\Exception $exception) {
            $this->logger->error('cannot fetch url ' . $uri, ['message' => $exception->getMessage(), 'file' => $exception->getFile()]);
            return null;
        }
    }

    private function buildDispatcher(): MiddlewareDispatcher
    {
        $requestHandler = GeneralUtility::makeInstance(RequestHandler::class);
        $middlewares = GeneralUtility::getContainer()->get('frontend.middlewares');
        return new MiddlewareDispatcher($requestHandler, $middlewares);
    }
}
