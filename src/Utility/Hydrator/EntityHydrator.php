<?php

namespace Cisse\Bundle\As400\Utility\Hydrator;

use Cisse\Bundle\As400\Attribute\Column;
use DateMalformedStringException;
use DateTime;
use ReflectionClass;
use ReflectionException;
use ReflectionProperty;

class EntityHydrator
{
    /**
     * @throws ReflectionException
     * @throws DateMalformedStringException
     */
    public static function hydrate(array $data, object|string $objectOrClass): object
    {
        $reflection = new ReflectionClass($objectOrClass);
        $entity = is_string($objectOrClass) ? new $objectOrClass() : $objectOrClass;

        foreach ($reflection->getProperties() as $property) {
            if ($property->getAttributes()) {
                foreach ($property->getAttributes() as $attribute) {
                    if ($attribute->getName() === Column::class) {
                        $reflectionProperty = new ReflectionProperty($objectOrClass, $property->getName());

                        $arguments = $attribute->getArguments();
                        $columnName = $arguments['name'] ?? $property->getName();
                        $columnType = $arguments['type'] ?? null;

                        $value = $data[$columnName] ?? null;

                        if ($value) {
                            if (is_string($value)) {
                                $value = trim($value);
                            }

                            if($columnType === Column::DATE_TYPE || $reflectionProperty->getType()?->getName() === 'DateTime'){
                                $value = new DateTime($value);
                            }
                        }

                        $property->setValue($entity, $value);
                    }
                }
            }
        }

        return $entity;
    }
}
