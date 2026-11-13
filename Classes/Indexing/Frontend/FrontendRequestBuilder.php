<?php

declare(strict_types=1);

namespace Lochmueller\Index\Indexing\Frontend;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\UriInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Core\Core\SystemEnvironmentBuilder;
use TYPO3\CMS\Core\Http\MiddlewareDispatcher;
use TYPO3\CMS\Core\Http\NormalizedParams;
use TYPO3\CMS\Core\Http\Response;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Frontend\Cache\CacheInstruction;
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
        protected Application $application,
        protected FrontendContextBuilder $frontendContextBuilder,
    ) {}

    public function buildRequestForPage(UriInterface $uri): ?string
    {
        $serverRequest = $this->createRequest($uri);

        try {
            $response = $this->frontendContextBuilder->executeInFrontendContext(function () use ($serverRequest) {
                $dispatcher = $this->buildDispatcher();
                return $dispatcher->handle($serverRequest);
            });

            if ($response->getStatusCode() !== 200) {
                // Error pages, redirects and access denied pages are not part of the index
                $this->logger?->warning('Skip url ' . $uri . ' because of a non 200 status code', ['status' => $response->getStatusCode()]);
                return null;
            }

            $response->getBody()->rewind();
            return $response->getBody()->getContents();
        } catch (\Exception $exception) {
            $this->logger?->error('cannot fetch url ' . $uri, ['message' => $exception->getMessage(), 'file' => $exception->getFile()]);
            return null;
        }
    }

    public function executeWithFrontendRequest(UriInterface $uri, callable $callback): mixed
    {
        $serverRequest = $this->createRequest($uri);
        $cacheInstruction = new CacheInstruction();
        $cacheInstruction->disableCache('Indexing needs frontend TypoScript without rendering or caching the page.');
        $serverRequest = $serverRequest->withAttribute('frontend.cache.instruction', $cacheInstruction);

        $callbackHandler = new class (\Closure::fromCallable($callback)) implements RequestHandlerInterface {
            private mixed $result = null;

            public function __construct(private readonly \Closure $callback) {}

            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                $this->result = ($this->callback)($request);
                return new Response();
            }

            public function getResult(): mixed
            {
                return $this->result;
            }
        };

        try {
            $this->frontendContextBuilder->executeInFrontendContext(function () use ($serverRequest, $callbackHandler): void {
                $this->buildDispatcher($callbackHandler)->handle($serverRequest);
            });
        } catch (\Exception $exception) {
            $this->logger?->warning('Unable to prepare frontend TypoScript context for ' . $uri, [
                'exception' => $exception,
            ]);
            return null;
        }

        return $callbackHandler->getResult();
    }

    protected function buildDispatcher(?RequestHandlerInterface $requestHandler = null): MiddlewareDispatcher
    {
        $requestHandler ??= GeneralUtility::makeInstance(RequestHandler::class);
        $middlewares = GeneralUtility::getContainer()->get('frontend.middlewares');
        return new MiddlewareDispatcher($requestHandler, $middlewares);
    }

    private function createRequest(UriInterface $uri): ServerRequestInterface
    {
        $serverParams = [
            'SCRIPT_NAME' => '/index.php',
            'HTTP_HOST' => $uri->getHost(),
            'SERVER_NAME' => $uri->getHost(),
            'HTTPS' => $uri->getScheme() === 'https' ? 'on' : 'off',
            'REMOTE_ADDR' => '127.0.0.1',
            'CONTEXT_DOCUMENT_ROOT' => Environment::getPublicPath(),
            'REQUEST_URI' => parse_url((string) $uri, PHP_URL_PATH),
        ];

        $serverRequest = new ServerRequest($uri, 'GET', null, [], $serverParams);
        if ($query = parse_url((string) $uri, PHP_URL_QUERY)) {
            parse_str($query, $queryResult);
            $serverRequest = $serverRequest->withQueryParams($queryResult);
        }

        return $serverRequest
            ->withAttribute('normalizedParams', NormalizedParams::createFromRequest($serverRequest))
            ->withAttribute('applicationType', SystemEnvironmentBuilder::REQUESTTYPE_FE);
    }
}
