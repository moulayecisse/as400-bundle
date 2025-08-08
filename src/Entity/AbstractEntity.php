<?php

namespace Cisse\Bundle\As400\Entity;

use Cisse\Bundle\As400\Utility\Hydrator\EntityDehydrator;

abstract class AbstractEntity implements As400EntityInterface
{
    public static function getFullTableName(): string
    {
        return static::DATABASE_NAME . '.' . static::TABLE_NAME;
    }

    public function __toArray(): array
    {
        return EntityDehydrator::dehydrate($this);
    }
}
