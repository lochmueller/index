<?php

declare(strict_types=1);

namespace Lochmueller\Index\Tests\Unit\Indexing\Field\Value\Transformer;

use Lochmueller\Index\Indexing\Field\Value\FieldValueContext;
use Lochmueller\Index\Indexing\Field\Value\Transformer\HtmlToTextTransformer;
use Lochmueller\Index\Indexing\Frontend\FrontendRequestBuilder;
use Lochmueller\Index\Tests\Unit\AbstractTest;
use TYPO3\CMS\Core\Core\SystemEnvironmentBuilder;
use TYPO3\CMS\Core\Domain\RecordInterface;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Core\Schema\Field\FieldTypeInterface;

final class HtmlToTextTransformerTest extends AbstractTest
{
    public function testUsesTypo3HtmlParserWhenNoFrontendTypoScriptRequestExists(): void
    {
        $record = self::createStub(RecordInterface::class);
        $record->method('getMainType')->willReturn('tx_example_domain_model_item');
        $record->method('getUid')->willReturn(42);
        $field = self::createStub(FieldTypeInterface::class);
        $field->method('getConfiguration')->willReturn(['enableRichtext' => true]);
        $request = (new ServerRequest('https://example.com/', 'GET'))
            ->withAttribute('applicationType', SystemEnvironmentBuilder::REQUESTTYPE_BE);
        $context = new FieldValueContext(
            record: $record,
            tcaField: $field,
            targetField: 'description',
            sourceField: 'description',
            definition: ['source' => 'description', 'type' => 'text', 'multiple' => false],
            configuration: [],
            value: '<p>First</p><p>Second<br>line &amp; more</p>',
            request: $request,
        );

        $transformer = new HtmlToTextTransformer(self::createStub(FrontendRequestBuilder::class));

        self::assertSame('First Second line & more', $transformer->transform($context));
    }
}
