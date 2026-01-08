<?php

namespace Cisse\Bundle\As400\Utility\Resolver\Entity;

use Cisse\Bundle\As400\Attribute\Entity;
use ReflectionClass;
use ReflectionException;

class ArgumentsResolver
{
    /**
     * Cache for entity arguments to avoid repeated reflection.
     *
     * @var array<string, array>
     */
    private static array $cache = [];

    /**
     * Resolve entity attribute arguments with caching.
     *
     * @throws ReflectionException
     */
    public static function resolve(object|string $entityOrClass): array
    {
        $className = is_string($entityOrClass) ? $entityOrClass : $entityOrClass::class;

        // Return cached result if available
        if (isset(self::$cache[$className])) {
            return self::$cache[$className];
        }

        $reflection = new ReflectionClass($className);
        $arguments = [];

        foreach ($reflection->getAttributes(Entity::class) as $attribute) {
            $arguments = $attribute->getArguments();
            break;
        }

        // Cache the result
        self::$cache[$className] = $arguments;

        return $arguments;
    }

    /**
     * Clear the cache (useful for testing).
     */
    public static function clearCache(): void
    {
        self::$cache = [];
    }
}
