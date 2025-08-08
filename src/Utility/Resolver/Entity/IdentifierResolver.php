<?php

namespace Cisse\Bundle\As400\Utility\Resolver\Entity;

use ReflectionException;

class IdentifierResolver
{
    /**
     * @throws ReflectionException
     */
    public static function resolve(object|string $entityOrClass): string
    {
        return ArgumentsResolver::resolve($entityOrClass)['identifier'] ?? 'id';
    }
}
