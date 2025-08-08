<?php

namespace Cisse\Bundle\As400\Utility\Resolver\Entity;

use Cisse\Bundle\As400\Attribute\Entity;
use ReflectionClass;
use ReflectionException;

class ArgumentsResolver
{
    /**
     * @throws ReflectionException
     */
    public static function resolve(object|string $entityOrClass): array
    {
        $reflection = new ReflectionClass($entityOrClass);

        foreach ($reflection->getAttributes() ?? [] as $attribute) {
            if ($attribute->getName() === Entity::class) {
                return $attribute->getArguments();
            }
        }

        return [];
    }
}
