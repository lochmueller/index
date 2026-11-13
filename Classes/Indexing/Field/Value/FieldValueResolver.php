<?php

declare(strict_types=1);

namespace Lochmueller\Index\Indexing\Field\Value;

use Lochmueller\Index\Indexing\Field\Value\Resolver\FieldResolverInterface;
use Lochmueller\Index\Indexing\Field\Value\Transformer\FieldTransformerInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;
use TYPO3\CMS\Core\Domain\RecordInterface;
use TYPO3\CMS\Core\Schema\TcaSchema;
use TYPO3\CMS\Core\Site\Entity\SiteInterface;

#[Autoconfigure(public: true)]
final class FieldValueResolver implements LoggerAwareInterface
{
    use LoggerAwareTrait;

    /**
     * @param iterable<string, FieldResolverInterface> $resolvers
     * @param iterable<string, FieldTransformerInterface> $transformers
     */
    public function __construct(
        #[AutowireIterator('index.field_resolver', indexAttribute: 'index')]
        private iterable $resolvers = [],
        #[AutowireIterator('index.field_transformer', indexAttribute: 'index')]
        private iterable $transformers = [],
    ) {}

    /**
     * @param array<string|int, mixed> $fieldConfiguration
     * @param array<string, array{source: string, type: string, multiple: bool}> $definitions
     * @param array<string, mixed> $values
     * @return array<string, mixed>
     */
    public function resolve(
        RecordInterface $record,
        TcaSchema $schema,
        array $fieldConfiguration,
        array $definitions,
        array $values,
        ?SiteInterface $site = null,
        ?int $languageId = null,
        ?ServerRequestInterface $request = null,
    ): array {
        $resolvers = $this->getServices($this->resolvers);
        $transformers = $this->getServices($this->transformers);

        foreach ($definitions as $targetField => $definition) {
            $configuration = $fieldConfiguration[$targetField] ?? [];
            if (is_string($configuration)) {
                $configuration = [];
            }
            if (!is_array($configuration)) {
                $this->logger?->warning('Ignoring invalid field configuration; using default field strategies.', [
                    'table' => $record->getMainType(),
                    'recordUid' => $record->getUid(),
                    'field' => $targetField,
                    'configurationType' => get_debug_type($configuration),
                ]);
                $configuration = [];
            }

            $context = new FieldValueContext(
                record: $record,
                tcaField: $schema->getField($definition['source']),
                targetField: $targetField,
                sourceField: $definition['source'],
                definition: $definition,
                configuration: $configuration,
                value: $values[$targetField] ?? null,
                site: $site,
                languageId: $languageId,
                request: $request,
            );

            try {
                $value = $this->resolveFieldValue($context, $resolvers);
            } catch (\InvalidArgumentException|\UnexpectedValueException $exception) {
                $this->logger?->warning('Indexed field resolution failed; using its source value.', [
                    'table' => $record->getMainType(),
                    'recordUid' => $record->getUid(),
                    'field' => $targetField,
                    'exception' => $exception,
                ]);
                $value = $context->value;
            }

            $context = $context->withValue($value);
            try {
                $value = $this->transformFieldValue($context, $transformers);
            } catch (\InvalidArgumentException|\UnexpectedValueException $exception) {
                $this->logger?->warning('Indexed field transformation failed; using the resolved value.', [
                    'table' => $record->getMainType(),
                    'recordUid' => $record->getUid(),
                    'field' => $targetField,
                    'exception' => $exception,
                ]);
                $value = $context->value;
            }
            $values[$targetField] = $value;
        }

        return $values;
    }

    /**
     * @template T of object
     * @param iterable<string, T> $services
     * @return array<string, T>
     */
    private function getServices(iterable $services): array
    {
        return is_array($services) ? $services : iterator_to_array($services);
    }

    /**
     * @param array<string, FieldResolverInterface> $resolvers
     */
    private function resolveFieldValue(FieldValueContext $context, array $resolvers): mixed
    {
        $identifier = $context->configuration['resolve'] ?? null;
        if ($identifier !== null) {
            if (!is_string($identifier)) {
                throw new \InvalidArgumentException('The "resolve" option for field "' . $context->targetField . '" must be a resolver identifier.');
            }
            $resolver = $resolvers[$identifier] ?? null;
            if (!$resolver instanceof FieldResolverInterface) {
                throw new \InvalidArgumentException('No field resolver is registered as "' . $identifier . '".');
            }

            return $resolver->resolve($context);
        }

        $matches = [];
        foreach ($resolvers as $resolver) {
            if ($resolver->supportsByDefault($context)) {
                $matches[] = $resolver;
            }
        }
        if (count($matches) > 1) {
            throw new \UnexpectedValueException('More than one default resolver supports field "' . $context->targetField . '". Configure "resolve" explicitly.');
        }

        return $matches === [] ? $context->value : $matches[0]->resolve($context);
    }

    /**
     * @param array<string, FieldTransformerInterface> $transformers
     */
    private function transformFieldValue(FieldValueContext $context, array $transformers): mixed
    {
        $identifier = $context->configuration['transform'] ?? null;
        if ($identifier !== null) {
            if (!is_string($identifier)) {
                throw new \InvalidArgumentException('The "transform" option for field "' . $context->targetField . '" must be a transformer identifier.');
            }
            $transformer = $transformers[$identifier] ?? null;
            if (!$transformer instanceof FieldTransformerInterface) {
                throw new \InvalidArgumentException('No field transformer is registered as "' . $identifier . '".');
            }

            return $transformer->transform($context);
        }

        $matches = [];
        foreach ($transformers as $transformer) {
            if ($transformer->supportsByDefault($context)) {
                $matches[] = $transformer;
            }
        }
        if (count($matches) > 1) {
            throw new \UnexpectedValueException('More than one default transformer supports field "' . $context->targetField . '". Configure "transform" explicitly.');
        }

        return $matches === [] ? $context->value : $matches[0]->transform($context);
    }
}
