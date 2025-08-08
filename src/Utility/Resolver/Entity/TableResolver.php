<?php

namespace Cisse\Bundle\As400\Utility\Resolver\Entity;

use ReflectionClass;
use ReflectionException;

class TableResolver
{
    /**
     * @throws ReflectionException
     */
    public static function resolve(object|string $entityOrClass): string
    {
        return
            ArgumentsResolver::resolve($entityOrClass)['table']
            ?? new ReflectionClass($entityOrClass)->getShortName();
    }
}
