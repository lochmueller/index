<?php

declare(strict_types=1);

namespace Lochmueller\Index\Indexing\Frontend;

use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use TYPO3\CMS\Core\Authentication\AbstractUserAuthentication;
use TYPO3\CMS\Core\Core\Environment;

class FrontendContextBuilder implements LoggerAwareInterface
{
    use LoggerAwareTrait;

    protected bool $isCliRequest = false;
    private ?AbstractUserAuthentication $originalUser = null;

    public function __construct()
    {
        $this->isCliRequest = Environment::isCli();
    }

    public function executeInFrontendContext(callable $callback): mixed
    {
        $this->prepare();
        try {
            $result = $callback();
            $this->restore();
            return $result;
        } catch (\Exception $exception) {
            $this->restore();
            $this->logger->error($exception->getMessage(), ['exception' => $exception]);
            throw $exception;
        }
    }


    private array $backedUpEnvironment = [];

    private function prepare(): void
    {
        $this->originalUser = $GLOBALS['BE_USER'];
        $this->initializeEnvironmentForNonCliCall(false);

        $GLOBALS['BE_USER'] = null;
        unset($GLOBALS['TSFE']);
    }

    private function restore(): void
    {
        $GLOBALS['BE_USER'] = $this->originalUser;
        unset($GLOBALS['TSFE']);
        $this->initializeEnvironmentForNonCliCall($this->isCliRequest);
    }

    private function initializeEnvironmentForNonCliCall(bool $cli): void
    {
        Environment::initialize(
            Environment::getContext(),
            $cli,
            Environment::isComposerMode(),
            Environment::getProjectPath(),
            Environment::getPublicPath(),
            Environment::getVarPath(),
            Environment::getConfigPath(),
            Environment::getPublicPath() . '/index.php',
            Environment::isWindows() ? 'WINDOWS' : 'UNIX',
        );
    }
}
