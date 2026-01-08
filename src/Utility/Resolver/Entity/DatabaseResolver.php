<?php

namespace Cisse\Bundle\As400\Utility\Resolver\Entity;

use ReflectionException;

class DatabaseResolver
{
    private static ?array $schemaMapping = null;

    /**
     * Cache for resolved database names (after schema mapping).
     *
     * @var array<string, string>
     */
    private static array $cache = [];

    /**
     * Set the schema mapping configuration.
     * This allows remapping logical schema names to physical ones (e.g., DICADCDE => ICADCDE).
     *
     * @param array $mapping Array with logical schema names as keys and physical names as values
     */
    public static function setSchemaMapping(array $mapping): void
    {
        self::$schemaMapping = $mapping;
        // Clear cache when mapping changes
        self::$cache = [];
    }

    /**
     * Get the current schema mapping configuration.
     *
     * @return array|null
     */
    public static function getSchemaMapping(): ?array
    {
        return self::$schemaMapping;
    }

    /**
     * Resolve the database/schema name for an entity, applying schema mapping if configured.
     *
     * @throws ReflectionException
     */
    public static function resolve(object|string $entityOrClass): string
    {
        $className = is_string($entityOrClass) ? $entityOrClass : $entityOrClass::class;

        if (isset(self::$cache[$className])) {
            return self::$cache[$className];
        }

        $database = ArgumentsResolver::resolve($className)['database'] ?? '';

        // Apply schema mapping if available
        if (self::$schemaMapping !== null && isset(self::$schemaMapping[$database])) {
            $database = self::$schemaMapping[$database];
        }

        self::$cache[$className] = $database;

        return $database;
    }

    /**
     * Clear the cache (useful for testing).
     */
    public static function clearCache(): void
    {
        self::$cache = [];
    }
}
