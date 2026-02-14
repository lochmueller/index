<?php

declare(strict_types=1);

namespace Lochmueller\Index\EventListener;

use Lochmueller\Index\Event\FinishIndexProcessEvent;
use Lochmueller\Index\Event\IndexFileEvent;
use Lochmueller\Index\Event\IndexPageEvent;
use Lochmueller\Index\Event\StartIndexProcessEvent;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use TYPO3\CMS\Core\Attribute\AsEventListener;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
use TYPO3\CMS\Core\Core\Environment;

final class DebugFileWriterEventListener implements LoggerAwareInterface
{
    use LoggerAwareTrait;

    public function __construct(
        private readonly ExtensionConfiguration $extensionConfiguration,
    ) {}

    #[AsEventListener('index-debug-file-writer')]
    public function __invoke(
        StartIndexProcessEvent|IndexPageEvent|IndexFileEvent|FinishIndexProcessEvent $event,
    ): void {
        try {
            if (!$this->isEnabled()) {
                return;
            }

            $data = $this->extractEventData($event);
            $directoryPath = $this->buildDirectoryPath(
                $event->site->getIdentifier(),
                $event->indexProcessId,
            );

            if (!is_dir($directoryPath)) {
                mkdir($directoryPath, 0o777, true);
            }

            $json = json_encode(
                $data,
                JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR,
            );

            $filePath = $directoryPath . '/' . $this->buildFileName($event);
            file_put_contents($filePath, $json);
        } catch (\Throwable $throwable) {
            $this->logger?->warning('Debug file writer failed: ' . $throwable->getMessage(), [
                'exception' => $throwable,
            ]);
        }
    }

    private function isEnabled(): bool
    {
        try {
            return true;
            return (bool) $this->extensionConfiguration->get('index', 'enableDebugFileWriter');
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function extractEventData(
        StartIndexProcessEvent|IndexPageEvent|IndexFileEvent|FinishIndexProcessEvent $event,
    ): array {
        $shortClassName = (new \ReflectionClass($event))->getShortName();

        $data = [
            'eventType' => $shortClassName,
            'site' => $event->site->getIdentifier(),
        ];

        if ($event instanceof StartIndexProcessEvent) {
            $data['technology'] = $event->technology->value;
            $data['type'] = $event->type->value;
            $data['indexConfigurationRecordId'] = $event->indexConfigurationRecordId;
            $data['indexProcessId'] = $event->indexProcessId;
            $data['startTime'] = microtime(true);
        } elseif ($event instanceof IndexPageEvent) {
            $data['technology'] = $event->technology->value;
            $data['type'] = $event->type->value;
            $data['indexConfigurationRecordId'] = $event->indexConfigurationRecordId;
            $data['indexProcessId'] = $event->indexProcessId;
            $data['language'] = $event->language;
            $data['title'] = $event->title;
            $data['content'] = $event->content;
            $data['pageUid'] = $event->pageUid;
            $data['accessGroups'] = $event->accessGroups;
            $data['uri'] = $event->uri;
        } elseif ($event instanceof IndexFileEvent) {
            $data['indexConfigurationRecordId'] = $event->indexConfigurationRecordId;
            $data['indexProcessId'] = $event->indexProcessId;
            $data['title'] = $event->title;
            $data['content'] = $event->content;
            $data['fileIdentifier'] = $event->fileIdentifier;
            $data['uri'] = $event->uri;
        } elseif ($event instanceof FinishIndexProcessEvent) {
            $data['technology'] = $event->technology->value;
            $data['type'] = $event->type->value;
            $data['indexConfigurationRecordId'] = $event->indexConfigurationRecordId;
            $data['indexProcessId'] = $event->indexProcessId;
            $data['endTime'] = microtime(true);
        }

        return $data;
    }

    private function buildDirectoryPath(string $siteIdentifier, string $indexProcessId): string
    {
        return Environment::getVarPath() . '/index-debug/' . $siteIdentifier . '/' . $indexProcessId;
    }

    private function buildFileName(
        StartIndexProcessEvent|IndexPageEvent|IndexFileEvent|FinishIndexProcessEvent $event,
    ): string {
        $shortClassName = (new \ReflectionClass($event))->getShortName();

        return $shortClassName . '_' . microtime(true) . '.txt';
    }
}
