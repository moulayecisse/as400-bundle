<?php

namespace Cisse\Bundle\As400\Utility\Resolver\Entity;

use ReflectionException;

class DatabaseResolver
{
    private static ?array $schemaMapping = null;

    /**
     * Set the schema mapping configuration.
     * This allows remapping logical schema names to physical ones (e.g., DICADCDE => ICADCDE).
     *
     * @param array $mapping Array with logical schema names as keys and physical names as values
     */
    public static function setSchemaMapping(array $mapping): void
    {
        self::$schemaMapping = $mapping;
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
        $database = ArgumentsResolver::resolve($entityOrClass)['database'] ?? '';

        // Apply schema mapping if available
        if (self::$schemaMapping !== null && isset(self::$schemaMapping[$database])) {
            return self::$schemaMapping[$database];
        }

        return $database;
    }
}
