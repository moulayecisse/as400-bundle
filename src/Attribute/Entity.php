<?php

namespace Cisse\Bundle\As400\Attribute;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
class Entity
{
    public function __construct(
        public string|null $table = null,
        public string|null $identifier = null,
        public string|null $database = null
    )
    {
    }
}
