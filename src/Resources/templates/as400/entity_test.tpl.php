<?php

declare(strict_types=1);

namespace {{ testNamespace }};

use {{ entityNamespace }}\{{ className }};
use PHPUnit\Framework\TestCase;

class {{ className }}Test extends TestCase
{
    public function testConstantsAreDefined(): void
    {
        $this->assertSame('{{ database }}', {{ className }}::DATABASE_NAME);
        $this->assertSame('{{ table }}', {{ className }}::TABLE_NAME);
        $this->assertSame('{{ identifier }}', {{ className }}::IDENTIFIER_NAME);
    }

    public function testColumnConstantsAreDefined(): void
    {
{{ columnConstantAssertions }}
    }

    public function testPropertiesCanBeSet(): void
    {
        $entity = new {{ className }}();

{{ propertySetStatements }}

{{ propertyAssertions }}
    }

    public function testAllPropertiesDefaultToNull(): void
    {
        $entity = new {{ className }}();

{{ nullAssertions }}
    }
}
