<?php

declare(strict_types=1);

namespace Lochmueller\Index\Indexing\Fetch;

use Lochmueller\Index\Configuration\ConfigurationLoader;
use Lochmueller\Index\ContentProcessing\ContentProcessor;
use Lochmueller\Index\Event\IndexFileEvent;
use Lochmueller\Index\Event\IndexPageEvent;
use Lochmueller\Index\FileExtraction\FileExtractor;
use Lochmueller\Index\Indexing\IndexingInterface;
use Lochmueller\Index\Queue\Message\FetchIndexMessage;
use Lochmueller\Index\Utility\FetchUtility;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use TYPO3\CMS\Core\Site\Entity\SiteInterface;
use TYPO3\CMS\Core\Site\SiteFinder;

class FetchIndexingHandler implements IndexingInterface, LoggerAwareInterface
{
    use LoggerAwareTrait;

    public function __construct(
        private readonly SiteFinder               $siteFinder,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly FetchUtility             $fetchUtility,
        private readonly FileExtractor            $fileExtractor,
        private readonly ContentProcessor         $contentProcessor,
        private readonly ConfigurationLoader      $configurationLoader,
    ) {}

    #[AsMessageHandler]
    public function __invoke(FetchIndexMessage $message): void
    {
        try {
            $site = $this->siteFinder->getSiteByIdentifier($message->siteIdentifier);
            $configuration = $this->configurationLoader->loadByUid($message->indexConfigurationRecordId);
            $contentProcessors = $configuration->contentProcessors ?? [];
            $fileExtensions = $message->skipFiles ? [] : $this->fileExtractor->resolveFileTypes($configuration->fileTypes ?? []);
        } catch (\Exception $exception) {
            $this->logger?->error($exception->getMessage(), ['exception' => $exception]);
            return;
        }

        $startUrl = $this->fetchUtility->removeFragment($message->uri);
        $urlBase = $this->fetchUtility->getUrlBase($startUrl);

        /** @var array<string, true> $visited */
        $visited = [$startUrl => true];
        /** @var array<int, array{string, int}> $queue */
        $queue = [[$startUrl, 0]];

        while ($queue !== []) {
            [$url, $level] = array_shift($queue);
            try {
                if ($this->isFileUrl($url, $fileExtensions)) {
                    $this->indexFile($message, $site, $url, $contentProcessors);
                    continue;
                }

                $links = $this->indexPage($message, $site, $url, $contentProcessors);
            } catch (\Exception $exception) {
                $this->logger?->error($exception->getMessage(), ['exception' => $exception, 'url' => $url]);
                continue;
            }

            foreach ($links as $link) {
                if (isset($visited[$link]) || !$this->fetchUtility->hasSameUrlBase($link, $urlBase)) {
                    continue;
                }
                // Files are leaves, so they are also indexed if they are linked on the last level
                if ($level >= $message->depth && !$this->isFileUrl($link, $fileExtensions)) {
                    continue;
                }
                $visited[$link] = true;
                $queue[] = [$link, $level + 1];
            }
        }
    }

    /**
     * @param class-string[] $contentProcessors
     * @return string[] Absolute links of the page
     */
    protected function indexPage(FetchIndexMessage $message, SiteInterface $site, string $url, array $contentProcessors): array
    {
        $response = $this->fetchUtility->download($url);
        if ($response === null || !$response->isHtml()) {
            return [];
        }

        $html = $this->fetchUtility->makeUrlsAbsolute($response->content, $url);
        $links = $this->fetchUtility->extractLinks($html, $url);

        $this->eventDispatcher->dispatch(new IndexPageEvent(
            site: $site,
            technology: $message->technology,
            type: $message->type,
            indexConfigurationRecordId: $message->indexConfigurationRecordId,
            indexProcessId: $message->indexProcessId,
            language: $message->language,
            title: $this->fetchUtility->extractTitle($html),
            content: $this->contentProcessor->process($html, $contentProcessors),
            pageUid: -1,
            accessGroups: [],
            uri: $url,
        ));

        return $links;
    }

    /**
     * @param class-string[] $contentProcessors
     */
    protected function indexFile(FetchIndexMessage $message, SiteInterface $site, string $url, array $contentProcessors): void
    {
        $response = $this->fetchUtility->download($url);
        if ($response === null) {
            return;
        }

        $fileName = rawurldecode(basename((string) parse_url($url, PHP_URL_PATH)));
        $content = $fileName;
        if (str_starts_with($response->contentType, 'text/plain')) {
            $content .= ' ' . $response->content;
        }

        $this->eventDispatcher->dispatch(new IndexFileEvent(
            site: $site,
            indexConfigurationRecordId: $message->indexConfigurationRecordId,
            indexProcessId: $message->indexProcessId,
            title: pathinfo($fileName, PATHINFO_FILENAME),
            content: $this->contentProcessor->process($content, $contentProcessors),
            fileIdentifier: '',
            uri: $url,
        ));
    }

    /**
     * @param string[] $fileExtensions
     */
    protected function isFileUrl(string $url, array $fileExtensions): bool
    {
        if ($fileExtensions === []) {
            return false;
        }
        $extension = strtolower(pathinfo((string) parse_url($url, PHP_URL_PATH), PATHINFO_EXTENSION));
        return $extension !== '' && in_array($extension, $fileExtensions, true);
    }
}
