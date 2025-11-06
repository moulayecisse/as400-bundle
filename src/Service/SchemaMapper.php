<?php

namespace Cisse\Bundle\As400\Service;

use Cisse\Bundle\As400\Utility\Resolver\Entity\DatabaseResolver;

/**
 * Service responsible for initializing and managing AS400 schema mapping.
 *
 * This service is automatically instantiated during container compilation
 * and configures the DatabaseResolver with the schema mapping from configuration.
 */
class SchemaMapper
{
    /**
     * @param array $schemaMapping Map of logical schema names to physical schema names
     */
    public function __construct(array $schemaMapping = [])
    {
        if (!empty($schemaMapping)) {
            DatabaseResolver::setSchemaMapping($schemaMapping);
        }
    }

    /**
     * Get the current schema mapping configuration.
     *
     * @return array|null
     */
    public function getSchemaMapping(): ?array
    {
        return DatabaseResolver::getSchemaMapping();
    }

    /**
     * Update the schema mapping at runtime (useful for testing or dynamic configuration).
     *
     * @param array $schemaMapping
     */
    public function updateSchemaMapping(array $schemaMapping): void
    {
        DatabaseResolver::setSchemaMapping($schemaMapping);
    }
}
