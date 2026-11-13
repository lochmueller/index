<?php

declare(strict_types=1);

namespace Lochmueller\Index\Indexing\Field\Value\Transformer;

use Lochmueller\Index\Indexing\Field\Value\FieldValueContext;
use Lochmueller\Index\Indexing\Frontend\FrontendRequestBuilder;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Symfony\Component\DependencyInjection\Attribute\AsTaggedItem;
use TYPO3\CMS\Core\Html\HtmlParser;
use TYPO3\CMS\Core\Http\ApplicationType;
use TYPO3\CMS\Core\Site\Entity\Site;
use TYPO3\CMS\Core\TypoScript\FrontendTypoScript;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Frontend\ContentObject\ContentObjectRenderer;

#[AsTaggedItem(index: 'html_to_text')]
final class HtmlToTextTransformer implements FieldTransformerInterface, LoggerAwareInterface
{
    use LoggerAwareTrait;

    public function __construct(
        private readonly FrontendRequestBuilder $frontendRequestBuilder,
    ) {}

    public function supportsByDefault(FieldValueContext $context): bool
    {
        return (bool) ($context->tcaField->getConfiguration()['enableRichtext'] ?? false);
    }

    public function transform(FieldValueContext $context): mixed
    {
        if ($context->definition['type'] !== 'text') {
            throw new \InvalidArgumentException('Rich-text conversion requires a text SEAL field: "' . $context->targetField . '".');
        }

        return $this->toPlainText($context, $context->value);
    }

    private function toPlainText(FieldValueContext $context, mixed $value): mixed
    {
        if (is_array($value)) {
            return array_map(fn(mixed $item): mixed => $this->toPlainText($context, $item), $value);
        }
        if (!is_string($value)) {
            return $value;
        }

        $html = $this->parseWithTypoScript($context, $value);
        $parser = GeneralUtility::makeInstance(HtmlParser::class);
        $parts = $parser->splitTags('br,hr', $html);
        foreach ($parts as $index => $part) {
            if ($index % 2 === 1) {
                $parts[$index] = ' ';
            }
        }
        $html = $this->removeBlockTags($parser, implode('', $parts));
        $plainText = $parser->HTMLcleaner($html, [], 0, 0);
        $plainText = html_entity_decode($plainText, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $plainText = preg_replace('/[\p{Z}\s]+/u', ' ', trim($plainText));

        return is_string($plainText) ? $plainText : trim($value);
    }

    private function parseWithTypoScript(FieldValueContext $context, string $html): string
    {
        $request = $context->request;
        if (!$request instanceof ServerRequestInterface) {
            $globalRequest = $GLOBALS['TYPO3_REQUEST'] ?? null;
            $request = $globalRequest instanceof ServerRequestInterface ? $globalRequest : null;
        }

        if ($request instanceof ServerRequestInterface && $this->hasFrontendTypoScript($request)) {
            return $this->renderWithTypoScript($context, $request, $html);
        }

        if ($context->site instanceof Site) {
            $pageUid = match ($context->record->getMainType()) {
                'pages' => $context->record->getUid(),
                'tt_content' => $context->record->getPid(),
                default => $context->site->getRootPageId(),
            };
            $arguments = $context->languageId === null ? [] : ['_language' => $context->languageId];
            $uri = $context->site->getRouter()->generateUri($pageUid, $arguments);
            $rendered = $this->frontendRequestBuilder->executeWithFrontendRequest(
                $uri,
                fn(ServerRequestInterface $frontendRequest): ?string => $this->hasFrontendTypoScript($frontendRequest)
                    ? $this->renderWithTypoScript($context, $frontendRequest, $html)
                    : null,
            );
            if (is_string($rendered)) {
                return $rendered;
            }
        }

        $this->logger?->debug('Using HtmlParser fallback because frontend TypoScript could not be prepared.', [
            'table' => $context->record->getMainType(),
            'recordUid' => $context->record->getUid(),
            'field' => $context->targetField,
        ]);

        return $html;
    }

    private function hasFrontendTypoScript(?ServerRequestInterface $request): bool
    {
        return $request instanceof ServerRequestInterface
            && !ApplicationType::fromRequest($request)->isBackend()
            && $request->getAttribute('frontend.typoscript') instanceof FrontendTypoScript
            && $request->getAttribute('frontend.typoscript')->hasSetup();
    }

    private function renderWithTypoScript(
        FieldValueContext $context,
        ServerRequestInterface $request,
        string $html,
    ): string {
        try {
            $contentObject = GeneralUtility::makeInstance(ContentObjectRenderer::class);
            $contentObject->setRequest($request);
            $contentObject->start($context->record->toArray(), $context->record->getMainType());
            return $contentObject->parseFunc($html, null, '< lib.parseFunc_RTE');
        } catch (\Throwable $exception) {
            $this->logger?->warning('lib.parseFunc_RTE failed; using TYPO3 HtmlParser for the field.', [
                'table' => $context->record->getMainType(),
                'recordUid' => $context->record->getUid(),
                'field' => $context->targetField,
                'exception' => $exception,
            ]);
            return $html;
        }
    }

    private function removeBlockTags(HtmlParser $parser, string $html): string
    {
        $blockTags = 'address,article,blockquote,dd,div,dl,dt,h1,h2,h3,h4,h5,h6,li,ol,p,pre,section,table,td,th,tr,ul';
        $parts = $parser->splitIntoBlock($blockTags, $html, true);
        foreach ($parts as $index => $part) {
            if ($index % 2 !== 1) {
                continue;
            }
            $parts[$index] = ' ' . $this->removeBlockTags($parser, $parser->removeFirstAndLastTag($part)) . ' ';
        }

        return implode('', $parts);
    }
}
