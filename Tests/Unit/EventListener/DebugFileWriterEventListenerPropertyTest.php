<?php

declare(strict_types=1);

namespace Lochmueller\Index\Tests\Unit\EventListener;

use Lochmueller\Index\Enums\IndexTechnology;
use Lochmueller\Index\Enums\IndexType;
use Lochmueller\Index\Event\FinishIndexProcessEvent;
use Lochmueller\Index\Event\IndexFileEvent;
use Lochmueller\Index\Event\IndexPageEvent;
use Lochmueller\Index\Event\StartIndexProcessEvent;
use Lochmueller\Index\EventListener\DebugFileWriterEventListener;
use Lochmueller\Index\Tests\Unit\AbstractTest;
use PHPUnit\Framework\Attributes\DataProvider;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Core\Site\Entity\SiteInterface;

class DebugFileWriterEventListenerPropertyTest extends AbstractTest
{
    protected function tearDown(): void
    {
        $debugDir = Environment::getVarPath() . '/index-debug';
        if (is_dir($debugDir)) {
            $this->removeDirectory($debugDir);
        }
        parent::tearDown();
    }

    private function removeDirectory(string $path): void
    {
        $items = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($path, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );
        foreach ($items as $item) {
            if ($item->isDir()) {
                rmdir($item->getPathname());
            } else {
                unlink($item->getPathname());
            }
        }
        rmdir($path);
    }

    private static function randomString(int $minLength = 1, int $maxLength = 30): string
    {
        $length = random_int($minLength, $maxLength);
        $chars = 'abcdefghijklmnopqrstuvwxyz0123456789-_';
        $result = '';
        for ($i = 0; $i < $length; $i++) {
            $result .= $chars[random_int(0, strlen($chars) - 1)];
        }

        return $result;
    }

    private static function randomTechnology(): IndexTechnology
    {
        $cases = IndexTechnology::cases();

        return $cases[array_rand($cases)];
    }

    private static function randomType(): IndexType
    {
        $cases = IndexType::cases();

        return $cases[array_rand($cases)];
    }

    private static function createRandomSiteStub(): SiteInterface
    {
        $identifier = self::randomString(3, 20);
        $stub = (new self('stub'))->createStub(SiteInterface::class);
        $stub->method('getIdentifier')->willReturn($identifier);

        return $stub;
    }

    private static function createRandomStartIndexProcessEvent(SiteInterface $site): StartIndexProcessEvent
    {
        return new StartIndexProcessEvent(
            site: $site,
            technology: self::randomTechnology(),
            type: self::randomType(),
            indexConfigurationRecordId: random_int(0, 9999),
            indexProcessId: self::randomString(5, 40),
            startTime: microtime(true) + random_int(-100000, 100000),
        );
    }

    private static function createRandomIndexPageEvent(SiteInterface $site): IndexPageEvent
    {
        $accessGroupCount = random_int(1, 5);
        $accessGroups = [];
        for ($i = 0; $i < $accessGroupCount; $i++) {
            $accessGroups[] = random_int(0, 100);
        }

        return new IndexPageEvent(
            site: $site,
            technology: self::randomTechnology(),
            type: self::randomType(),
            indexConfigurationRecordId: random_int(0, 9999),
            indexProcessId: self::randomString(5, 40),
            language: random_int(0, 10),
            title: self::randomString(1, 100),
            content: self::randomString(0, 500),
            pageUid: random_int(1, 99999),
            accessGroups: $accessGroups,
            uri: 'https://example.com/' . self::randomString(1, 50),
        );
    }

    private static function createRandomIndexFileEvent(SiteInterface $site): IndexFileEvent
    {
        return new IndexFileEvent(
            site: $site,
            indexConfigurationRecordId: random_int(0, 9999),
            indexProcessId: self::randomString(5, 40),
            title: self::randomString(1, 100),
            content: self::randomString(0, 500),
            fileIdentifier: '1:/' . self::randomString(3, 30) . '/' . self::randomString(3, 20) . '.pdf',
            uri: 'https://example.com/' . self::randomString(1, 50),
        );
    }

    private static function createRandomFinishIndexProcessEvent(SiteInterface $site): FinishIndexProcessEvent
    {
        return new FinishIndexProcessEvent(
            site: $site,
            technology: self::randomTechnology(),
            type: self::randomType(),
            indexConfigurationRecordId: random_int(0, 9999),
            indexProcessId: self::randomString(5, 40),
            endTime: microtime(true) + random_int(-100000, 100000),
        );
    }

    /**
     * @return \Generator<string, array{0: StartIndexProcessEvent|IndexPageEvent|IndexFileEvent|FinishIndexProcessEvent}>
     */
    public static function disabledFeatureRandomEventsDataProvider(): \Generator
    {
        $eventFactories = [
            'StartIndexProcessEvent' => [self::class, 'createRandomStartIndexProcessEvent'],
            'IndexPageEvent' => [self::class, 'createRandomIndexPageEvent'],
            'IndexFileEvent' => [self::class, 'createRandomIndexFileEvent'],
            'FinishIndexProcessEvent' => [self::class, 'createRandomFinishIndexProcessEvent'],
        ];

        for ($i = 0; $i < self::TEST_ITERATIONS; $i++) {
            $eventTypeName = array_rand($eventFactories);
            $site = self::createRandomSiteStub();
            $event = $eventFactories[$eventTypeName]($site);

            yield sprintf('Iteration %d: %s', $i + 1, $eventTypeName) => [$event];
        }
    }

    /**
     * @return \Generator<string, array{0: string, 1: string}>
     */
    public static function directoryPathComponentsDataProvider(): \Generator
    {
        for ($i = 0; $i < self::TEST_ITERATIONS; $i++) {
            $siteIdentifier = self::randomString(3, 20);
            $indexProcessId = self::randomString(5, 40);

            yield sprintf('Iteration %d: site=%s processId=%s', $i + 1, $siteIdentifier, $indexProcessId) => [
                $siteIdentifier,
                $indexProcessId,
            ];
        }
    }

    /**
     * @return \Generator<string, array{0: StartIndexProcessEvent|IndexPageEvent|IndexFileEvent|FinishIndexProcessEvent}>
     */
    public static function eventDataExtractionDataProvider(): \Generator
    {
        $eventFactories = [
            'StartIndexProcessEvent' => [self::class, 'createRandomStartIndexProcessEvent'],
            'IndexPageEvent' => [self::class, 'createRandomIndexPageEvent'],
            'IndexFileEvent' => [self::class, 'createRandomIndexFileEvent'],
            'FinishIndexProcessEvent' => [self::class, 'createRandomFinishIndexProcessEvent'],
        ];

        for ($i = 0; $i < self::TEST_ITERATIONS; $i++) {
            $eventTypeName = array_rand($eventFactories);
            $site = self::createRandomSiteStub();
            $event = $eventFactories[$eventTypeName]($site);

            yield sprintf('Iteration %d: %s', $i + 1, $eventTypeName) => [$event, $eventTypeName];
        }
    }



    /**
     * Property 1: Deaktiviertes Feature erzeugt keine Ausgabe
     *
     * Für zufällig generierte Events aller vier Typen: bei deaktiviertem Feature
     * keine Dateisystem-Operationen (kein Verzeichnis unter var/index-debug/).
     */
    #[DataProvider('disabledFeatureRandomEventsDataProvider')]
    public function testDisabledFeatureProducesNoOutput(
        StartIndexProcessEvent|IndexPageEvent|IndexFileEvent|FinishIndexProcessEvent $event,
    ): void {
        $extensionConfiguration = $this->createStub(ExtensionConfiguration::class);
        $extensionConfiguration->method('get')->willReturn('0');

        $subject = new DebugFileWriterEventListener($extensionConfiguration);
        $subject($event);

        $debugDir = Environment::getVarPath() . '/index-debug';
        self::assertDirectoryDoesNotExist($debugDir);
    }

    /**
     * Property 3: Verzeichnispfad enthält Basispfad, Site-Identifier und indexProcessId
     *
     * Für zufällig generierte Site-Identifier und indexProcessIds: der erzeugte
     * Verzeichnispfad enthält alle Bestandteile in korrekter Reihenfolge:
     * {varPath}/index-debug/{siteIdentifier}/{indexProcessId}
     */
    #[DataProvider('directoryPathComponentsDataProvider')]
    public function testDirectoryPathContainsAllComponents(
        string $siteIdentifier,
        string $indexProcessId,
    ): void {
        $site = $this->createStub(SiteInterface::class);
        $site->method('getIdentifier')->willReturn($siteIdentifier);

        $event = new StartIndexProcessEvent(
            site: $site,
            technology: self::randomTechnology(),
            type: self::randomType(),
            indexConfigurationRecordId: random_int(0, 9999),
            indexProcessId: $indexProcessId,
            startTime: microtime(true),
        );

        $extensionConfiguration = $this->createStub(ExtensionConfiguration::class);
        $extensionConfiguration->method('get')->willReturn('1');

        $subject = new DebugFileWriterEventListener($extensionConfiguration);
        $subject($event);

        $varPath = Environment::getVarPath();
        $expectedDir = $varPath . '/index-debug/' . $siteIdentifier . '/' . $indexProcessId;

        // Verify directory was created
        self::assertDirectoryExists($expectedDir);

        // Verify correct order: varPath → index-debug → site → processId
        $relativePath = str_replace($varPath, '', $expectedDir);
        $segments = explode('/', trim($relativePath, '/'));

        self::assertSame('index-debug', $segments[0], 'First segment must be index-debug');
        self::assertSame($siteIdentifier, $segments[1], 'Second segment must be site identifier');
        self::assertSame($indexProcessId, $segments[2], 'Third segment must be indexProcessId');

        // Verify base path is the var path
        self::assertStringStartsWith($varPath . '/index-debug/', $expectedDir);
    }

    /**
     * Property 4: Event-Daten-Extraktion erzeugt vollständiges JSON mit allen relevanten Eigenschaften
     *
     * Für zufällig generierte Events: extrahierte Daten enthalten alle erwarteten
     * Schlüssel je Event-Typ, JSON round-trip ist äquivalent.
     */
    #[DataProvider('eventDataExtractionDataProvider')]
    public function testEventDataExtractionProducesCompleteJson(
        StartIndexProcessEvent|IndexPageEvent|IndexFileEvent|FinishIndexProcessEvent $event,
        string $eventTypeName,
    ): void {
        $extensionConfiguration = $this->createStub(ExtensionConfiguration::class);
        $extensionConfiguration->method('get')->willReturn('1');

        $subject = new DebugFileWriterEventListener($extensionConfiguration);
        $subject($event);

        // Find the written file
        $debugDir = Environment::getVarPath() . '/index-debug/'
            . $event->site->getIdentifier() . '/' . $event->indexProcessId;
        self::assertDirectoryExists($debugDir);

        $files = glob($debugDir . '/' . $eventTypeName . '_*.txt');
        self::assertNotEmpty($files, 'Expected at least one file for ' . $eventTypeName);

        $fileContent = file_get_contents($files[0]);
        self::assertIsString($fileContent);

        $data = json_decode($fileContent, true, 512, JSON_THROW_ON_ERROR);
        self::assertIsArray($data);

        // Common keys for all event types
        self::assertArrayHasKey('eventType', $data);
        self::assertSame($eventTypeName, $data['eventType']);
        self::assertArrayHasKey('site', $data);
        self::assertSame($event->site->getIdentifier(), $data['site']);
        self::assertArrayHasKey('indexProcessId', $data);
        self::assertSame($event->indexProcessId, $data['indexProcessId']);

        // Type-specific keys
        $expectedKeys = match ($eventTypeName) {
            'StartIndexProcessEvent' => [
                'eventType', 'site', 'technology', 'type',
                'indexConfigurationRecordId', 'indexProcessId', 'startTime',
            ],
            'IndexPageEvent' => [
                'eventType', 'site', 'technology', 'type',
                'indexConfigurationRecordId', 'indexProcessId', 'language',
                'title', 'content', 'pageUid', 'accessGroups', 'uri',
            ],
            'IndexFileEvent' => [
                'eventType', 'site', 'indexConfigurationRecordId',
                'indexProcessId', 'title', 'content', 'fileIdentifier', 'uri',
            ],
            'FinishIndexProcessEvent' => [
                'eventType', 'site', 'technology', 'type',
                'indexConfigurationRecordId', 'indexProcessId', 'endTime',
            ],
        };

        foreach ($expectedKeys as $key) {
            self::assertArrayHasKey($key, $data, sprintf(
                'Missing key "%s" in %s JSON output',
                $key,
                $eventTypeName,
            ));
        }

        // JSON round-trip: json_decode(json_encode($data)) produces equivalent data
        $reEncoded = json_encode($data, JSON_THROW_ON_ERROR);
        $reDecoded = json_decode($reEncoded, true, 512, JSON_THROW_ON_ERROR);
        self::assertSame($data, $reDecoded, 'JSON round-trip must produce equivalent data');
    }

    /**
     * @return \Generator<string, array{0: StartIndexProcessEvent|IndexPageEvent|IndexFileEvent|FinishIndexProcessEvent}>
     */
    public static function jsonFormattingDataProvider(): \Generator
    {
        $eventFactories = [
            'StartIndexProcessEvent' => [self::class, 'createRandomStartIndexProcessEvent'],
            'IndexPageEvent' => [self::class, 'createRandomIndexPageEvent'],
            'IndexFileEvent' => [self::class, 'createRandomIndexFileEvent'],
            'FinishIndexProcessEvent' => [self::class, 'createRandomFinishIndexProcessEvent'],
        ];

        for ($i = 0; $i < self::TEST_ITERATIONS; $i++) {
            $eventTypeName = array_rand($eventFactories);
            $site = self::createRandomSiteStub();
            $event = $eventFactories[$eventTypeName]($site);

            yield sprintf('Iteration %d: %s', $i + 1, $eventTypeName) => [$event];
        }
    }

    /**
     * Property 5: JSON-Ausgabe ist menschenlesbar formatiert
     *
     * Für zufällig generierte Event-Daten: JSON-Ausgabe enthält Zeilenumbrüche
     * und Einrückungen, keine escaped Unicode-Sequenzen und keine escaped Slashes.
     */
    #[DataProvider('jsonFormattingDataProvider')]
    public function testJsonOutputIsHumanReadableFormatted(
        StartIndexProcessEvent|IndexPageEvent|IndexFileEvent|FinishIndexProcessEvent $event,
    ): void {
        $extensionConfiguration = $this->createStub(ExtensionConfiguration::class);
        $extensionConfiguration->method('get')->willReturn('1');

        $subject = new DebugFileWriterEventListener($extensionConfiguration);
        $subject($event);

        // Find the written file
        $debugDir = Environment::getVarPath() . '/index-debug/'
            . $event->site->getIdentifier() . '/' . $event->indexProcessId;
        self::assertDirectoryExists($debugDir);

        $files = glob($debugDir . '/*.txt');
        self::assertNotEmpty($files, 'Expected at least one written file');

        $rawContent = file_get_contents($files[0]);
        self::assertIsString($rawContent);

        // JSON_PRETTY_PRINT: content contains newlines
        self::assertStringContainsString("\n", $rawContent, 'Pretty-printed JSON must contain newlines');

        // JSON_PRETTY_PRINT: content contains 4-space indentation
        self::assertMatchesRegularExpression('/^    /m', $rawContent, 'Pretty-printed JSON must contain 4-space indentation');

        // JSON_UNESCAPED_UNICODE: no escaped unicode sequences like \uXXXX
        self::assertDoesNotMatchRegularExpression('/\\\\u[0-9a-fA-F]{4}/', $rawContent, 'JSON must not contain escaped unicode sequences');

        // JSON_UNESCAPED_SLASHES: no escaped slashes
        self::assertDoesNotMatchRegularExpression('/\\\\\//', $rawContent, 'JSON must not contain escaped slashes');
    }

    /**
     * @return \Generator<string, array{0: StartIndexProcessEvent|IndexPageEvent|IndexFileEvent|FinishIndexProcessEvent, 1: string}>
     */
    public static function filenamePatternDataProvider(): \Generator
    {
        $eventFactories = [
            'StartIndexProcessEvent' => [self::class, 'createRandomStartIndexProcessEvent'],
            'IndexPageEvent' => [self::class, 'createRandomIndexPageEvent'],
            'IndexFileEvent' => [self::class, 'createRandomIndexFileEvent'],
            'FinishIndexProcessEvent' => [self::class, 'createRandomFinishIndexProcessEvent'],
        ];

        for ($i = 0; $i < self::TEST_ITERATIONS; $i++) {
            $eventTypeName = array_rand($eventFactories);
            $site = self::createRandomSiteStub();
            $event = $eventFactories[$eventTypeName]($site);

            yield sprintf('Iteration %d: %s', $i + 1, $eventTypeName) => [$event, $eventTypeName];
        }
    }

    /**
     * Property 6: Dateiname enthält Event-Typ und Zeitstempel
     *
     * Für zufällig generierte Events: Dateiname matcht Pattern
     * {EventKlassenname}_{numerisch}.txt
     */
    #[DataProvider('filenamePatternDataProvider')]
    public function testFilenameContainsEventTypeAndTimestamp(
        StartIndexProcessEvent|IndexPageEvent|IndexFileEvent|FinishIndexProcessEvent $event,
        string $eventTypeName,
    ): void {
        $extensionConfiguration = $this->createStub(ExtensionConfiguration::class);
        $extensionConfiguration->method('get')->willReturn('1');

        $subject = new DebugFileWriterEventListener($extensionConfiguration);
        $subject($event);

        // Find the written file
        $debugDir = Environment::getVarPath() . '/index-debug/'
            . $event->site->getIdentifier() . '/' . $event->indexProcessId;
        self::assertDirectoryExists($debugDir);

        $files = glob($debugDir . '/*.txt');
        self::assertNotEmpty($files, 'Expected at least one written file');

        $filename = basename($files[0]);

        // 1. Filename matches the full regex pattern
        self::assertMatchesRegularExpression(
            '/^(StartIndexProcessEvent|IndexPageEvent|IndexFileEvent|FinishIndexProcessEvent)_[0-9]+(\.[0-9]+)?\.txt$/',
            $filename,
            'Filename must match pattern {EventClassName}_{numeric}.txt',
        );

        // 2. Event class short name in filename matches the actual event type
        $filenameParts = explode('_', $filename, 2);
        self::assertSame(
            $eventTypeName,
            $filenameParts[0],
            'Event class name in filename must match the actual event type',
        );

        // 3. Filename ends with .txt
        self::assertStringEndsWith('.txt', $filename, 'Filename must end with .txt');

        // 4. Timestamp part is a valid numeric value
        $timestampPart = substr($filenameParts[1], 0, -4); // Remove .txt
        self::assertIsNumeric($timestampPart, 'Timestamp part must be a valid numeric value');
        self::assertGreaterThan(0, (float) $timestampPart, 'Timestamp must be a positive number');
    }




}
