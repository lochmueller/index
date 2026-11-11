<?php

declare(strict_types=1);

namespace Lochmueller\Index\Tests\Unit\Indexing\Record;

use Lochmueller\Index\Indexing\Record\RecordFieldDefinitionResolver;
use Lochmueller\Index\Tests\Unit\AbstractTest;
use TYPO3\CMS\Core\Schema\Field\FieldTypeInterface;
use TYPO3\CMS\Core\Schema\TcaSchema;
use TYPO3\CMS\Core\Schema\TcaSchemaFactory;

class RecordFieldDefinitionResolverTest extends AbstractTest
{
    public function testResolveInfersIntegerTypeFromTcaInputEval(): void
    {
        $field = self::createStub(FieldTypeInterface::class);
        $field->method('getType')->willReturn('input');
        $field->method('getConfiguration')->willReturn(['eval' => 'trim,int']);
        $field->method('isSearchable')->willReturn(false);

        $schema = self::createStub(TcaSchema::class);
        $schema->method('hasField')->willReturn(true);
        $schema->method('getField')->willReturn($field);

        $schemaFactory = self::createStub(TcaSchemaFactory::class);
        $schemaFactory->method('get')->willReturn($schema);

        $definitions = (new RecordFieldDefinitionResolver($schemaFactory))->resolve('tx_example_domain_model_item', [
            'departmentId' => ['source' => 'department'],
        ]);

        self::assertSame('department', $definitions['departmentId']['source']);
        self::assertSame('integer', $definitions['departmentId']['type']);
        self::assertFalse($definitions['departmentId']['searchable']);
    }

    public function testResolveRejectsUnknownTcaField(): void
    {
        $schema = self::createStub(TcaSchema::class);
        $schema->method('hasField')->willReturn(false);

        $schemaFactory = self::createStub(TcaSchemaFactory::class);
        $schemaFactory->method('get')->willReturn($schema);

        $this->expectException(\InvalidArgumentException::class);
        (new RecordFieldDefinitionResolver($schemaFactory))->resolve('tx_example_domain_model_item', [
            'title' => ['source' => 'missing_field'],
        ]);
    }

    public function testExtractValuesKeepsRawValuesForEventListenersToResolve(): void
    {
        $field = self::createStub(FieldTypeInterface::class);
        $field->method('getType')->willReturn('input');
        $field->method('getConfiguration')->willReturn([]);
        $field->method('isSearchable')->willReturn(false);

        $schema = self::createStub(TcaSchema::class);
        $schema->method('hasField')->willReturn(true);
        $schema->method('getField')->willReturn($field);

        $schemaFactory = self::createStub(TcaSchemaFactory::class);
        $schemaFactory->method('get')->willReturn($schema);

        $resolver = new RecordFieldDefinitionResolver($schemaFactory);
        $definitions = $resolver->resolve('tx_example_domain_model_item', [
            'title' => 'title',
            'departments' => ['source' => 'department_ids', 'multiple' => true],
        ]);
        $values = $resolver->extractValues([
            'title' => 'Example',
            'department_ids' => '42,43',
        ], $definitions);

        self::assertSame('42,43', $values['departments']);
        self::assertSame([
            'title' => 'Example',
            'departments' => ['Sales', 'Service'],
        ], $resolver->normalizeValues([
            'title' => $values['title'],
            'departments' => ['Sales', 'Service'],
        ], $definitions));
    }

    public function testTitleIsSearchableByDefaultEvenIfTheTcaFieldIsNot(): void
    {
        $field = self::createStub(FieldTypeInterface::class);
        $field->method('getType')->willReturn('input');
        $field->method('getConfiguration')->willReturn([]);
        $field->method('isSearchable')->willReturn(false);

        $schema = self::createStub(TcaSchema::class);
        $schema->method('hasField')->willReturn(true);
        $schema->method('getField')->willReturn($field);

        $schemaFactory = self::createStub(TcaSchemaFactory::class);
        $schemaFactory->method('get')->willReturn($schema);

        $definitions = (new RecordFieldDefinitionResolver($schemaFactory))->resolve('tx_example_domain_model_item', [
            'title' => 'title',
        ]);

        self::assertTrue($definitions['title']['searchable']);
    }

    public function testNormalizeValuesPreservesFalseBooleanValues(): void
    {
        $resolver = new RecordFieldDefinitionResolver(self::createStub(TcaSchemaFactory::class));

        self::assertSame(
            ['enabled' => false],
            $resolver->normalizeValues(['enabled' => 'false'], [
                'enabled' => ['type' => 'boolean', 'multiple' => false],
            ]),
        );
    }

    public function testNormalizeValuesRejectsFractionalIntegerValues(): void
    {
        $resolver = new RecordFieldDefinitionResolver(self::createStub(TcaSchemaFactory::class));

        $this->expectException(\UnexpectedValueException::class);
        $resolver->normalizeValues(['count' => 1.5], [
            'count' => ['type' => 'integer', 'multiple' => false],
        ]);
    }
}
