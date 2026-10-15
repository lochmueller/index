<?php

declare(strict_types=1);

namespace Lochmueller\Index\Tests\Unit\Configuration;

use Lochmueller\Index\Configuration\ConfigurationResolver;
use Lochmueller\Index\Tests\Unit\AbstractTest;
use Psr\Log\NullLogger;
use TYPO3\CMS\Core\Configuration\Loader\YamlFileLoader;

class ConfigurationResolverTest extends AbstractTest
{
    private const FIXTURE_PATH = __DIR__ . '/Fixtures/';

    protected function tearDown(): void
    {
        putenv('INDEX_TEST_STORAGE');
        parent::tearDown();
    }

    public function testResolveReturnsEmptyArrayForInvalidJson(): void
    {
        self::assertSame([], $this->createSubject()->resolve('{ invalid'));
        self::assertSame([], $this->createSubject()->resolve(''));
    }

    public function testResolveReturnsConfigurationWithoutImport(): void
    {
        $result = $this->createSubject()->resolve('{"extender": [{"type": "news"}]}');

        self::assertSame(['extender' => [['type' => 'news']]], $result);
    }

    public function testResolveImportsJsonFile(): void
    {
        $result = $this->createSubject()->resolve($this->createImportJson(self::FIXTURE_PATH . 'base.json'));

        self::assertSame([
            'extender' => [
                ['type' => 'news', 'limitToPages' => [13], 'recordStorages' => [12]],
            ],
            'options' => ['a' => 'base', 'b' => 'base'],
        ], $result);
    }

    public function testResolveMergesImportsInOrderAndRecordConfigurationWins(): void
    {
        $json = (string) json_encode([
            '@import' => [self::FIXTURE_PATH . 'base.json', self::FIXTURE_PATH . 'override.json'],
            'options' => ['c' => 'record'],
        ]);

        $result = $this->createSubject()->resolve($json);

        self::assertSame([['type' => 'address']], $result['extender']);
        self::assertSame(['a' => 'base', 'b' => 'override', 'c' => 'record'], $result['options']);
        self::assertArrayNotHasKey('@import', $result);
    }

    public function testResolveReplacesListsInsteadOfMerging(): void
    {
        $json = (string) json_encode([
            '@import' => self::FIXTURE_PATH . 'base.json',
            'extender' => [['type' => 'calendarize']],
        ]);

        $result = $this->createSubject()->resolve($json);

        self::assertSame([['type' => 'calendarize']], $result['extender']);
    }

    public function testResolveImportsYamlFileWithEnvironmentPlaceholder(): void
    {
        putenv('INDEX_TEST_STORAGE=24');

        $result = $this->createSubject()->resolve($this->createImportJson(self::FIXTURE_PATH . 'environment.yaml'));

        self::assertSame([
            'extender' => [
                ['type' => 'news', 'limitToPages' => [13], 'recordStorages' => ['24']],
            ],
        ], $result);
    }

    public function testResolveImportsNestedFilesRelativeToImportingFile(): void
    {
        $result = $this->createSubject()->resolve($this->createImportJson(self::FIXTURE_PATH . 'nested.json'));

        self::assertSame(['options' => ['a' => 'nested', 'b' => 'child']], $result);
    }

    public function testResolveIgnoresRecursiveImport(): void
    {
        $result = $this->createSubject()->resolve($this->createImportJson(self::FIXTURE_PATH . 'recursive.json'));

        self::assertSame(['options' => ['a' => 'recursive']], $result);
    }

    public function testResolveIgnoresMissingFile(): void
    {
        $json = (string) json_encode([
            '@import' => self::FIXTURE_PATH . 'missing.json',
            'options' => ['a' => 'record'],
        ]);

        self::assertSame(['options' => ['a' => 'record']], $this->createSubject()->resolve($json));
    }

    public function testResolveIgnoresInvalidAndUnsupportedFiles(): void
    {
        $json = (string) json_encode([
            '@import' => [self::FIXTURE_PATH . 'invalid.json', self::FIXTURE_PATH . 'unsupported.txt'],
            'options' => ['a' => 'record'],
        ]);

        self::assertSame(['options' => ['a' => 'record']], $this->createSubject()->resolve($json));
    }

    public function testResolveIgnoresFilesOutsideOfProject(): void
    {
        $file = tempnam(sys_get_temp_dir(), 'index') . '.json';
        file_put_contents($file, '{"options": {"a": "outside"}}');

        try {
            $result = $this->createSubject()->resolve($this->createImportJson($file));
        } finally {
            unlink($file);
        }

        self::assertSame([], $result);
    }

    public function testResolveIgnoresInvalidImportValues(): void
    {
        self::assertSame(['a' => 1], $this->createSubject()->resolve('{"@import": 5, "a": 1}'));
        self::assertSame(['a' => 1], $this->createSubject()->resolve('{"@import": [5, ""], "a": 1}'));
    }

    private function createImportJson(string $file): string
    {
        return (string) json_encode(['@import' => $file]);
    }

    private function createSubject(): ConfigurationResolver
    {
        $subject = new ConfigurationResolver(new YamlFileLoader(new NullLogger()));
        $subject->setLogger(new NullLogger());

        return $subject;
    }
}
