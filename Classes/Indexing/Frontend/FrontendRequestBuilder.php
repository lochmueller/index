<?php

declare(strict_types=1);

namespace Lochmueller\Index\Indexing\Frontend;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\UriInterface;
use Psr\Log\LoggerInterface;
use TYPO3\CMS\Core\Core\Environment;
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
class FrontendRequestBuilder
{
    public function __construct(protected Application $application, protected LoggerInterface $logger) {}

    private $originalUser;

    private array $backedUpEnvironment = [];

    private function prepare(): void
    {
        $this->originalUser = $GLOBALS['BE_USER'];
        $this->backupEnvironment();
        $this->initializeEnvironmentForNonCliCall(Environment::getContext());

        $GLOBALS['BE_USER'] = null;
        unset($GLOBALS['TSFE']);
    }

    private function restore(): void
    {
        $GLOBALS['BE_USER'] = $this->originalUser;
        unset($GLOBALS['TSFE']);
        $this->restoreEnvironment();
    }

    public function executeFrontendRequest(ServerRequestInterface $request): ResponseInterface
    {
        $dispatcher = $this->buildDispatcher();
        return $dispatcher->handle($request);
    }

    private function buildDispatcher(): MiddlewareDispatcher
    {
        $requestHandler = GeneralUtility::makeInstance(RequestHandler::class);
        $middlewares = GeneralUtility::getContainer()->get('frontend.middlewares');
        return new MiddlewareDispatcher($requestHandler, $middlewares);
    }

    public function buildRequestForPage(UriInterface $uri, $frontendUserGroups = []): ?string
    {
        $serverParams = [
            'SCRIPT_NAME' => '/index.php',
            'HTTP_HOST' => $uri->getHost(),
            'SERVER_NAME' => $uri->getHost(),
            'HTTPS' => $uri->getScheme() === 'https' ? 'on' : 'off',
            'REMOTE_ADDR' => '127.0.0.1',
        ];
        $headers = [];
        $serverRequest = new ServerRequest($uri, 'GET', null, $headers, $serverParams);
        $serverRequest = $serverRequest->withAttribute('normalizedParams', NormalizedParams::createFromRequest($serverRequest));

        $serverRequest = $serverRequest->withAttribute('applicationType', SystemEnvironmentBuilder::REQUESTTYPE_FE);

        try {
            $response = $this->executeFrontendRequest($serverRequest);
            return $response->getBody()->getContents();
        } catch (\Exception $e) {
            $this->logger->error('cannot fetch url ' . (string) $uri, ['message' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            return null;
        }
    }

    private function initializeEnvironmentForNonCliCall(ApplicationContext $applicationContext): void
    {
        Environment::initialize(
            $applicationContext,
            false,
            Environment::isComposerMode(),
            Environment::getProjectPath(),
            Environment::getPublicPath(),
            Environment::getVarPath(),
            Environment::getConfigPath(),
            Environment::getCurrentScript(),
            Environment::isWindows() ? 'WINDOWS' : 'UNIX',
        );
    }

    /**
     * Helper method used in setUp() if $this->backupEnvironment is true
     * to back up current state of the Environment::class
     */
    private function backupEnvironment(): void
    {
        $this->backedUpEnvironment['context'] = Environment::getContext();
        $this->backedUpEnvironment['isCli'] = Environment::isCli();
        $this->backedUpEnvironment['composerMode'] = Environment::isComposerMode();
        $this->backedUpEnvironment['projectPath'] = Environment::getProjectPath();
        $this->backedUpEnvironment['publicPath'] = Environment::getPublicPath();
        $this->backedUpEnvironment['varPath'] = Environment::getVarPath();
        $this->backedUpEnvironment['configPath'] = Environment::getConfigPath();
        $this->backedUpEnvironment['currentScript'] = Environment::getCurrentScript();
        $this->backedUpEnvironment['isOsWindows'] = Environment::isWindows();
    }

    /**
     * Helper method used in tearDown() if $this->backupEnvironment is true
     * to reset state of Environment::class
     */
    private function restoreEnvironment(): void
    {
        Environment::initialize(
            $this->backedUpEnvironment['context'],
            $this->backedUpEnvironment['isCli'],
            $this->backedUpEnvironment['composerMode'],
            $this->backedUpEnvironment['projectPath'],
            $this->backedUpEnvironment['publicPath'],
            $this->backedUpEnvironment['varPath'],
            $this->backedUpEnvironment['configPath'],
            $this->backedUpEnvironment['currentScript'],
            $this->backedUpEnvironment['isOsWindows'] ? 'WINDOWS' : 'UNIX',
        );
    }
}
