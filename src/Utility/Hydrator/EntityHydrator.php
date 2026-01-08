<?php

namespace Cisse\Bundle\As400\Utility\Hydrator;

use Cisse\Bundle\As400\Attribute\Column;
use DateMalformedStringException;
use DateTime;
use ReflectionClass;
use ReflectionException;

class EntityHydrator
{
    /**
     * Cache for entity metadata to avoid repeated reflection.
     * Structure: [className => [columnName => ['property' => ReflectionProperty, 'type' => string|null]]]
     *
     * @var array<string, array<string, array{property: \ReflectionProperty, type: string|null}>>
     */
    private static array $metadataCache = [];

    /**
     * @throws ReflectionException
     * @throws DateMalformedStringException
     */
    public static function hydrate(array $data, object|string $objectOrClass): object
    {
        $className = is_string($objectOrClass) ? $objectOrClass : $objectOrClass::class;
        $entity = is_string($objectOrClass) ? new $objectOrClass() : $objectOrClass;

        // Get cached metadata or build it once
        $metadata = self::getMetadata($className);

        foreach ($metadata as $columnName => $columnMeta) {
            $value = $data[$columnName] ?? null;

            if ($value !== null) {
                if (is_string($value)) {
                    $value = trim($value);
                }

                if ($columnMeta['type'] === Column::DATE_TYPE || $columnMeta['type'] === 'DateTime') {
                    $value = new DateTime($value);
                }
            }

            $columnMeta['property']->setValue($entity, $value);
        }

        return $entity;
    }

    /**
     * Hydrate multiple rows efficiently using cached metadata.
     *
     * @param array<array<string, mixed>> $rows
     * @return array<object>
     * @throws ReflectionException
     * @throws DateMalformedStringException
     */
    public static function hydrateAll(array $rows, string $entityClass): array
    {
        if (empty($rows)) {
            return [];
        }

        // Pre-load metadata once for all rows
        $metadata = self::getMetadata($entityClass);
        $entities = [];

        foreach ($rows as $data) {
            $entity = new $entityClass();

            foreach ($metadata as $columnName => $columnMeta) {
                $value = $data[$columnName] ?? null;

                if ($value !== null) {
                    if (is_string($value)) {
                        $value = trim($value);
                    }

                    if ($columnMeta['type'] === Column::DATE_TYPE || $columnMeta['type'] === 'DateTime') {
                        $value = new DateTime($value);
                    }
                }

                $columnMeta['property']->setValue($entity, $value);
            }

            $entities[] = $entity;
        }

        return $entities;
    }

    /**
     * Generator-based hydration for memory-efficient processing of large datasets.
     *
     * @param iterable<array<string, mixed>> $rows
     * @return \Generator<object>
     * @throws ReflectionException
     */
    public static function hydrateIterator(iterable $rows, string $entityClass): \Generator
    {
        // Pre-load metadata once
        $metadata = self::getMetadata($entityClass);

        foreach ($rows as $data) {
            $entity = new $entityClass();

            foreach ($metadata as $columnName => $columnMeta) {
                $value = $data[$columnName] ?? null;

                if ($value !== null) {
                    if (is_string($value)) {
                        $value = trim($value);
                    }

                    if ($columnMeta['type'] === Column::DATE_TYPE || $columnMeta['type'] === 'DateTime') {
                        try {
                            $value = new DateTime($value);
                        } catch (DateMalformedStringException) {
                            $value = null;
                        }
                    }
                }

                $columnMeta['property']->setValue($entity, $value);
            }

            yield $entity;
        }
    }

    /**
     * Get cached metadata for an entity class.
     *
     * @return array<string, array{property: \ReflectionProperty, type: string|null}>
     * @throws ReflectionException
     */
    private static function getMetadata(string $className): array
    {
        if (isset(self::$metadataCache[$className])) {
            return self::$metadataCache[$className];
        }

        $reflection = new ReflectionClass($className);
        $metadata = [];

        foreach ($reflection->getProperties() as $property) {
            foreach ($property->getAttributes(Column::class) as $attribute) {
                $arguments = $attribute->getArguments();
                $columnName = $arguments['name'] ?? $property->getName();

                // Determine type from attribute or property type
                $columnType = $arguments['type'] ?? null;
                if ($columnType === null) {
                    $propertyType = $property->getType();
                    if ($propertyType !== null && $propertyType->getName() === 'DateTime') {
                        $columnType = 'DateTime';
                    }
                }

                // Make property accessible once
                $property->setAccessible(true);

                $metadata[$columnName] = [
                    'property' => $property,
                    'type' => $columnType,
                ];
            }
        }

        self::$metadataCache[$className] = $metadata;

        return $metadata;
    }

    /**
     * Clear the metadata cache (useful for testing).
     */
    public static function clearCache(): void
    {
        self::$metadataCache = [];
    }
}
