<?php

declare(strict_types=1);

namespace Lochmueller\Index\Indexing\Http;

use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\UriInterface;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use TYPO3\CMS\Core\Http\RequestFactory;

class HttpRequestBuilder implements LoggerAwareInterface
{
    use LoggerAwareTrait;

    public function __construct(protected ClientInterface $client, protected RequestFactory $requestFactory) {}

    public function buildRequestForPage(UriInterface $uri): string
    {
        $request = $this->requestFactory->createRequest('GET', $uri);
        try {
            $response = $this->client->sendRequest($request);
        } catch (\Exception $e) {
            $this->logger->error($e->getMessage(), ['exception' => $e]);
            return '';
        }
        if ($response->getStatusCode() !== 200) {
            return '';
        }

        return $response->getBody()->getContents();
    }
}
