<?php

namespace {{ namespace }};

use Cisse\Bundle\As400\Attribute\Column;
use Cisse\Bundle\As400\Attribute\Entity;
use Cisse\Bundle\As400\Entity\AbstractEntity;

#[Entity(table: self::TABLE_NAME, identifier: self::IDENTIFIER_NAME, database: self::DATABASE_NAME)]
class {{ className }} extends AbstractEntity
{
    const string DATABASE_NAME = '{{ database }}';
    const string TABLE_NAME = '{{ table }}';
    const string IDENTIFIER_NAME = '{{ identifier }}';

    {{ constants }}

    {{ properties }}
}
