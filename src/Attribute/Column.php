<?php

namespace Cisse\Bundle\As400\Attribute;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
class Column
{
    public const string STRING_TYPE = 'string';
    public const string INTEGER_TYPE = 'integer';
    public const string BOOLEAN_TYPE = 'boolean';
    public const string FLOAT_TYPE = 'float';
    public const string DATE_TYPE = 'date';
    public const string DATETIME_TYPE = 'datetime';
    public const string TIME_TYPE = 'time';
    public const string ARRAY_TYPE = 'array';
    public const string OBJECT_TYPE = 'object';
    public const string JSON_TYPE = 'json';
    public const string BLOB_TYPE = 'blob';
    public const string TEXT_TYPE = 'text';
    public const string BINARY_TYPE = 'binary';
    public const string ENUM_TYPE = 'enum';

    public function __construct(
        public string|null $name = null,
        public string|null $type = null,
        public bool $nullable = false,
        public bool $unique = false,
        public bool $primary = false,
        public bool $autoincrement = false,
        public bool $unsigned = false,
        public string|null $default = null,
        public string|null $comment = null,
    ) {
    }
}
