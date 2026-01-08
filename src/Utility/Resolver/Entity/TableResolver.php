<?php

namespace Cisse\Bundle\As400\Utility\Resolver\Entity;

use ReflectionClass;
use ReflectionException;

class TableResolver
{
    /**
     * Cache for table names.
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

        $arguments = ArgumentsResolver::resolve($className);
        $table = $arguments['table'] ?? (new ReflectionClass($className))->getShortName();

        self::$cache[$className] = $table;

        return $table;
    }

    /**
     * Clear the cache (useful for testing).
     */
    public static function clearCache(): void
    {
        self::$cache = [];
    }
}
