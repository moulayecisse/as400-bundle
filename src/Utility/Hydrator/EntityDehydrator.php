<?php

namespace Cisse\Bundle\As400\Utility\Hydrator;

use Cisse\Bundle\As400\Attribute\Column;
use DateTime;
use ReflectionClass;

class EntityDehydrator
{
    public static function dehydrate(object $entity): array
    {
        $reflection = new ReflectionClass($entity);

        $data = [];

        foreach ($reflection->getProperties() as $property) {
            if ($property->getAttributes()) {
                foreach ($property->getAttributes() as $attribute) {
                    if ($attribute->getName() === Column::class) {
                        $arguments = $attribute->getArguments();
                        $columnName = $arguments['name'] ?? $property->getName();
                        $columnType = $arguments['type'] ?? null;

                        $value = $property->getValue($entity);

                        if ($columnType === Column::DATE_TYPE && $value instanceof DateTime) {
                            $value = $value->format('Y-m-d');
                        }

                        $data[$columnName] = $value;
                    }
                }
            }
        }

        return $data;
    }
}
