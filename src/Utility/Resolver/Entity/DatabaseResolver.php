<?php

namespace Cisse\Bundle\As400\Utility\Resolver\Entity;

use ReflectionException;

class DatabaseResolver
{
    /**
     * @throws ReflectionException
     */
    public static function resolve(object|string $entityOrClass): string
    {
        return ArgumentsResolver::resolve($entityOrClass)['database'] ?? '';
    }
}
