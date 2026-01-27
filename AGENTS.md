# AGENTS.md - Coding Agent Guidelines for EXT:index

This document provides guidelines for AI coding agents working in this TYPO3 extension.

## Project Overview

**EXT:index** is a TYPO3 CMS extension providing async indexing of pages and documents for search engines and AI providers.

- **Package**: `lochmueller/index`
- **PHP**: `^8.3`
- **TYPO3**: `^13.4 || ^14.0`
- **Namespace**: `Lochmueller\Index\`

## Build, Lint, and Test Commands

All commands are run via Composer from the package root:

| Command | Description |
|---------|-------------|
| `composer code-fix` | Auto-fix code style with PHP-CS-Fixer |
| `composer code-check` | Run PHPStan static analysis |
| `composer code-test` | Run PHPUnit tests |

### Running a Single Test

```bash
# Run all tests
composer code-test

# Run a specific test file
.Build/bin/phpunit -c Tests/UnitTests.xml Tests/Unit/Path/To/YourTest.php

# Run a specific test method
.Build/bin/phpunit -c Tests/UnitTests.xml --filter testMethodName

# Run tests by group
.Build/bin/phpunit -c Tests/UnitTests.xml --group=tmp
```

## Code Style Guidelines

### PHP-CS-Fixer Configuration

- **Standard**: PER-CS3.0 with risky rules
- **Key rules**: `declare_strict_types`, `no_unused_imports`
- **Config file**: `.php-cs-fixer.dist.php`

### File Structure

Every PHP file must follow this structure:

```php
<?php

declare(strict_types=1);

namespace Lochmueller\Index\SubNamespace;

use Psr\Log\LoggerAwareInterface;
use TYPO3\CMS\Core\Utility\GeneralUtility;

final class ExampleClass implements SomeInterface
{
    public function __construct(
        private readonly DependencyInterface $dependency,
    ) {}

    public function doSomething(string $value): ?array
    {
        if ($value === '') {
            return null;
        }

        return $this->dependency->process($value);
    }
}
```

### Key Conventions

- `declare(strict_types=1)` is **required** at the top of every PHP file
- PSR-4 autoloading with `Lochmueller\Index\` namespace
- Use **constructor property promotion** with `readonly`
- Use `final` for handlers, messages, and events
- Use `final readonly` for message/DTO classes
- No Yoda conditions: `$value === ''` not `'' === $value`
- Single quotes for strings, short array syntax `[]`
- Trailing commas in multiline arrays and parameters
- Type hints required for all parameters and return types
- PHPDoc only when types cannot be expressed in PHP

### Import Organization

Imports are sorted alphabetically by PHP-CS-Fixer. Group by:
1. PHP core classes
2. PSR interfaces
3. Symfony components
4. TYPO3 core classes
5. Project classes

### Naming Conventions

| Type | Convention | Example |
|------|------------|---------|
| Classes | PascalCase | `DatabaseIndexingHandler` |
| Interfaces | PascalCase + `Interface` suffix | `ContentTypeInterface` |
| DTOs | PascalCase + `Dto` suffix | `DatabaseIndexingDto` |
| Events | PascalCase + `Event` suffix | `IndexPageEvent` |
| Messages | PascalCase + `Message` suffix | `DatabaseIndexMessage` |
| Handlers | PascalCase + `Handler` suffix | `StartProcessHandler` |
| Enums | PascalCase | `IndexTechnology` |
| Methods | camelCase | `loadByPage()` |
| Properties | camelCase | `$configurationCache` |

## Error Handling Patterns

### Early Returns for Invalid State

```php
if ($pageRow === null) {
    return;
}
if ($configuration->skipNoSearchPages && ($pageRow['no_search'] ?? false)) {
    return;
}
```

### Null Returns for "Not Found"

```php
public function loadByUid(int $uid): ?Configuration
{
    return self::$runtimeConfigurationCache[$uid] ?? null;
}
```

### LoggerAware Pattern

```php
class ContentIndexing implements LoggerAwareInterface
{
    use LoggerAwareTrait;

    public function addContent(Record $record): ?string
    {
        if ($result === null) {
            $this->logger->warning('Content could not be handled', [
                'record_type' => $record->getRecordType()
            ]);
        }
        return $result;
    }
}
```

## PHP 8 Attributes

This extension uses PHP 8 attributes extensively for DI and routing:

```php
#[AsMessageHandler]
final readonly class StartProcessHandler { ... }

#[AutoconfigureTag(name: 'index.content_type')]
interface ContentTypeInterface { ... }

#[AutowireIterator('index.content_type')]
protected readonly iterable $contentTypes,

#[AsCommand(name: 'index:queue', description: '...')]
class QueueCommand extends Command { ... }
```

## Test Guidelines

### Test Structure

```php
<?php

declare(strict_types=1);

namespace Lochmueller\Index\Tests\Unit\SubNamespace;

use Lochmueller\Index\Tests\Unit\AbstractTest;

class ExampleTest extends AbstractTest
{
    public function testMethodDoesExpectedBehavior(): void
    {
        $subject = new Example();
        self::assertSame('expected', $subject->process('input'));
    }
}
```

### Test Conventions

- Extend `AbstractTest` (which extends TYPO3's `UnitTestCase`)
- Test class naming: `{ClassName}Test`
- Test method naming: `test{Description}` (camelCase) and NO `#[Test]` attribute
- Use `self::` prefix for assertions: `self::assertSame()`, `self::assertEquals()`
- Use `#[Group('tmp')]` to mark tests for quick iteration
- Test files location: `Tests/Unit/{SubDirectory}/{ClassName}Test.php`
- Use Stubs instead of Mocks, if there are no expectations (avoid notices in the execution)

## Dependency Injection Tags

| Tag | Purpose |
|-----|---------|
| `index.content_type` | Register content element renderers |
| `index.file_extractor` | Register file content extractors |
| `index.extender` | Register URI queue extenders |

Configure in `Configuration/Services.yaml`:

```yaml
services:
  _defaults:
    autowire: true
    autoconfigure: true
    public: false

  Lochmueller\Index\:
    resource: '../Classes/*'
```

## Project Structure

```
Classes/
  Backend/          # TCA utilities
  Command/          # CLI commands (Symfony Console)
  Configuration/    # Configuration loading
  Enums/            # PHP 8.1 enums
  Event/            # PSR-14 events
  EventListener/    # Event listeners
  FileExtraction/   # File content extraction
  Hooks/            # TYPO3 hooks (DataHandler)
  Indexing/         # Core indexing logic (Cache, Database, Frontend, HTTP)
  Queue/            # Message queue (Handler, Message)
  Reaction/         # TYPO3 Reactions integration
  Traversing/       # Page/file traversing
  Webhooks/         # Webhook integration
Configuration/
  Services.yaml     # Symfony DI configuration
  TCA/              # Table Configuration Array
Tests/
  Unit/             # PHPUnit tests
  UnitTests.xml     # PHPUnit configuration
```

## Important Notes

1. **Always run linters before committing**: `composer code-fix && composer code-check`
2. **Static analysis level**: PHPStan Level 5
3. **TYPO3 DI**: Use constructor injection, configure in `Services.yaml`
4. **Database changes**: Update TCA files in `Configuration/TCA/`
5. **Vendor directory**: Located at `.Build/vendor/`
