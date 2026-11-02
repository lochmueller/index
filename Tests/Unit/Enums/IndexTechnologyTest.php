<?php

declare(strict_types=1);

namespace Lochmueller\Index\Tests\Unit\Enums;

use Lochmueller\Index\Enums\IndexTechnology;
use Lochmueller\Index\Tests\Unit\AbstractTest;

class IndexTechnologyTest extends AbstractTest
{
    public function testTechnologiesOfTheIndexConfigurationRecordAreTriggeredByAConfiguration(): void
    {
        self::assertTrue(IndexTechnology::Database->isTriggeredByConfiguration());
        self::assertTrue(IndexTechnology::Frontend->isTriggeredByConfiguration());
        self::assertTrue(IndexTechnology::Http->isTriggeredByConfiguration());
        self::assertTrue(IndexTechnology::Fetch->isTriggeredByConfiguration());
    }

    public function testTechnologiesWithAnOwnEntryPointAreNotTriggeredByAConfiguration(): void
    {
        self::assertFalse(IndexTechnology::None->isTriggeredByConfiguration());
        self::assertFalse(IndexTechnology::Cache->isTriggeredByConfiguration());
        self::assertFalse(IndexTechnology::External->isTriggeredByConfiguration());
    }
}
