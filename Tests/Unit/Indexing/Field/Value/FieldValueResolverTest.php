<?php

declare(strict_types=1);

namespace Lochmueller\Index\Tests\Unit\Indexing\Field\Value;

use Lochmueller\Index\Indexing\Field\Value\FieldValueContext;
use Lochmueller\Index\Indexing\Field\Value\FieldValueResolver;
use Lochmueller\Index\Indexing\Field\Value\Resolver\FieldResolverInterface;
use Lochmueller\Index\Tests\Unit\AbstractTest;
use Psr\Log\LoggerInterface;
use TYPO3\CMS\Core\Domain\RecordInterface;
use TYPO3\CMS\Core\Schema\Field\FieldTypeInterface;
use TYPO3\CMS\Core\Schema\TcaSchema;

final class FieldValueResolverTest extends AbstractTest
{
    public function testConfiguredResolverCanBeAddedThroughTheTaggedResolverCollection(): void
    {
        $resolver = new class implements FieldResolverInterface {
            public function supportsByDefault(FieldValueContext $context): bool
            {
                return false;
            }

            public function resolve(FieldValueContext $context): mixed
            {
                return strtoupper((string) $context->value);
            }
        };
        $fieldValueResolver = new FieldValueResolver(['uppercase' => $resolver]);
        $record = self::createStub(RecordInterface::class);
        $field = self::createStub(FieldTypeInterface::class);
        $schema = self::createStub(TcaSchema::class);
        $schema->method('getField')->willReturn($field);

        $values = $fieldValueResolver->resolve(
            $record,
            $schema,
            ['title' => ['source' => 'title', 'resolve' => 'uppercase']],
            ['title' => ['source' => 'title', 'type' => 'text', 'multiple' => false]],
            ['title' => 'indexed title'],
        );

        self::assertSame('INDEXED TITLE', $values['title']);
    }

    public function testUnknownResolverIsLoggedAndKeepsTheSourceValue(): void
    {
        $record = self::createStub(RecordInterface::class);
        $record->method('getMainType')->willReturn('pages');
        $record->method('getUid')->willReturn(42);
        $field = self::createStub(FieldTypeInterface::class);
        $schema = self::createStub(TcaSchema::class);
        $schema->method('getField')->willReturn($field);

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())
            ->method('warning')
            ->with(
                'Indexed field resolution failed; using its source value.',
                self::callback(static fn(array $context): bool => $context['field'] === 'title'),
            );

        $fieldValueResolver = new FieldValueResolver();
        $fieldValueResolver->setLogger($logger);
        $values = $fieldValueResolver->resolve(
            $record,
            $schema,
            ['title' => ['source' => 'title', 'resolve' => 'not_registered']],
            ['title' => ['source' => 'title', 'type' => 'text', 'multiple' => false]],
            ['title' => 'source title'],
        );

        self::assertSame('source title', $values['title']);
    }
}
