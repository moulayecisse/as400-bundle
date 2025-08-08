<?php

namespace {{ repositoryNamespace }};

use {{ entityNamespace }}\{{ className }};
use Cisse\Bundle\As400\Repository\Repository;

/**
 * @method {{ className }}|null find(int|string $id, array|null $fields = null, bool $hydrate = true)
 * @method {{ className }}|null findOneBy(array|string $criteria, array|string|null $orderBy = null, array|string|null $fields = null, bool $hydrate = true)
 * @method {{ className }}[]    findAll(array|null $fields = null, bool $hydrate = true)
 * @method {{ className }}[]    findBy(array|string $criteria = [], array|string|null $orderBy = null, $limit = null, int|null $offset = null, array|string|null $fields = null, bool $hydrate = true)
 */
class {{ className }}Repository extends Repository
{
    protected const string ENTITY_CLASS = {{ className }}::class;
}
