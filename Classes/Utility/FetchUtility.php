<?php

declare(strict_types=1);

namespace Lochmueller\Index\Utility;

use GuzzleHttp\Psr7\Uri;
use GuzzleHttp\Psr7\UriResolver;
use Psr\Http\Client\ClientInterface;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use TYPO3\CMS\Core\Http\RequestFactory;

/**
 * Utility class for downloading external pages and normalizing the URLs of the content.
 */
class FetchUtility implements LoggerAwareInterface
{
    use LoggerAwareTrait;

    protected const USER_AGENT = 'TYPO3 EXT:index Fetch';

    public function __construct(
        protected readonly ClientInterface $client,
        protected readonly RequestFactory $requestFactory,
    ) {}

    public function download(string $url): ?FetchResponseDto
    {
        if (!$this->isSupportedUrl($url)) {
            $this->logger?->warning('Fetch aborted because only http and https URLs are supported', ['url' => $url]);
            return null;
        }

        try {
            $request = $this->requestFactory->createRequest('GET', $url)
                ->withHeader('User-Agent', self::USER_AGENT);
            $response = $this->client->sendRequest($request);
        } catch (\Exception $exception) {
            $this->logger?->error($exception->getMessage(), ['exception' => $exception, 'url' => $url]);
            return null;
        }

        if ($response->getStatusCode() !== 200) {
            $this->logger?->warning('Fetch returned a non 200 status code', ['url' => $url, 'status' => $response->getStatusCode()]);
            return null;
        }

        return new FetchResponseDto(
            uri: $url,
            contentType: strtolower($response->getHeaderLine('Content-Type')),
            content: (string) $response->getBody(),
        );
    }

    public function isSupportedUrl(string $url): bool
    {
        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));

        return $scheme === 'http' || $scheme === 'https';
    }

    /**
     * Rewrite all href, src, action, poster and srcset attributes of the given HTML to absolute URLs.
     */
    public function makeUrlsAbsolute(string $html, string $pageUrl): string
    {
        $baseUrl = $this->getDocumentBaseUrl($html, $pageUrl);

        $html = (string) preg_replace_callback(
            '/(\s(?:href|src|action|poster)\s*=\s*)(["\'])(.*?)\2/is',
            fn(array $matches): string => $matches[1] . $matches[2] . $this->encodeAttribute($this->resolveUrl($this->decodeAttribute($matches[3]), $baseUrl)) . $matches[2],
            $html,
        );

        return (string) preg_replace_callback(
            '/(\ssrcset\s*=\s*)(["\'])(.*?)\2/is',
            function (array $matches) use ($baseUrl): string {
                $candidates = array_map(function (string $candidate) use ($baseUrl): string {
                    $parts = preg_split('/\s+/', trim($candidate), 2) ?: [''];
                    $parts[0] = $this->resolveUrl($parts[0], $baseUrl);
                    return implode(' ', $parts);
                }, explode(',', $this->decodeAttribute($matches[3])));

                return $matches[1] . $matches[2] . $this->encodeAttribute(implode(', ', $candidates)) . $matches[2];
            },
            $html,
        );
    }

    /**
     * Extract all unique absolute http(s) link targets (without fragment) of <a> tags.
     *
     * @return string[]
     */
    public function extractLinks(string $html, string $pageUrl): array
    {
        $baseUrl = $this->getDocumentBaseUrl($html, $pageUrl);

        if (!preg_match_all('/<a\s[^>]*?href\s*=\s*(["\'])(.*?)\1/is', $html, $matches)) {
            return [];
        }

        $links = [];
        foreach ($matches[2] as $href) {
            $url = $this->removeFragment($this->resolveUrl($this->decodeAttribute($href), $baseUrl));
            if (preg_match('/^https?:\/\//i', $url)) {
                $links[$url] = $url;
            }
        }

        return array_values($links);
    }

    public function extractTitle(string $html): string
    {
        if (preg_match('/<title\b[^>]*>([\s\S]*?)<\/title>/i', $html, $matches)) {
            return trim(html_entity_decode($matches[1], ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        }

        return '';
    }

    public function resolveUrl(string $url, string $baseUrl): string
    {
        $url = trim($url);
        if ($url === '' || str_starts_with($url, '#') || preg_match('/^(mailto|tel|javascript|data):/i', $url)) {
            return $url;
        }

        try {
            return (string) UriResolver::resolve(new Uri($baseUrl), new Uri($url));
        } catch (\InvalidArgumentException) {
            return $url;
        }
    }

    /**
     * Get the URL base (scheme, host, port and directory path) of the given URL.
     * A last path segment with a dot is handled as file, otherwise the path is handled as directory.
     * Example: https://example.com/docs/index.html -> https://example.com/docs/
     *          https://example.com/docs -> https://example.com/docs/
     */
    public function getUrlBase(string $url): string
    {
        $uri = new Uri($url);
        $path = $uri->getPath();
        $lastSlash = strrpos($path, '/');
        $lastSegment = $lastSlash === false ? $path : substr($path, $lastSlash + 1);

        if (str_contains($lastSegment, '.')) {
            $path = $lastSlash === false ? '/' : substr($path, 0, $lastSlash + 1);
        } else {
            $path = rtrim($path, '/') . '/';
        }

        return (string) $uri->withPath($path)->withQuery('')->withFragment('');
    }

    public function hasSameUrlBase(string $url, string $urlBase): bool
    {
        try {
            $uri = new Uri($url);
            $base = new Uri($urlBase);
        } catch (\InvalidArgumentException) {
            return false;
        }

        if (strtolower($uri->getScheme()) !== strtolower($base->getScheme())
            || strtolower($uri->getHost()) !== strtolower($base->getHost())
            || $uri->getPort() !== $base->getPort()) {
            return false;
        }

        $path = $uri->getPath() === '' ? '/' : $uri->getPath();
        $basePath = $base->getPath() === '' ? '/' : $base->getPath();

        return str_starts_with($path, $basePath) || $path . '/' === $basePath;
    }

    public function removeFragment(string $url): string
    {
        $position = strpos($url, '#');
        return $position === false ? $url : substr($url, 0, $position);
    }

    protected function getDocumentBaseUrl(string $html, string $pageUrl): string
    {
        if (preg_match('/<base\s[^>]*?href\s*=\s*(["\'])(.*?)\1/is', $html, $matches)) {
            return $this->resolveUrl($this->decodeAttribute($matches[2]), $pageUrl);
        }

        return $pageUrl;
    }

    protected function decodeAttribute(string $value): string
    {
        return html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    protected function encodeAttribute(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_HTML5, 'UTF-8', false);
    }
}
