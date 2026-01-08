<?php

namespace Cisse\Bundle\As400\Utility\Resolver\Entity;

use ReflectionException;

class IdentifierResolver
{
    /**
     * Cache for identifier names.
     *
     * @var array<string, string>
     */
    private static array $cache = [];

    /**
     * @throws ReflectionException
     */
    public static function resolve(object|string $entityOrClass): string
    {
        $className = is_string($entityOrClass) ? $entityOrClass : $entityOrClass::class;

        if (isset(self::$cache[$className])) {
            return self::$cache[$className];
        }

        $identifier = ArgumentsResolver::resolve($className)['identifier'] ?? 'id';

        self::$cache[$className] = $identifier;

        return $identifier;
    }

    /**
     * Clear the cache (useful for testing).
     */
    public static function clearCache(): void
    {
        self::$cache = [];
    }
}
