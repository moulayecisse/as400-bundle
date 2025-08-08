<?php

namespace Cisse\Bundle\As400\Utility;

class As400Utility
{
    public static function cleanAS400Values(mixed $value)
    {
        if (is_string($value)) {
            return self::encodeAS400Values(trim($value));
        }

        if (!is_iterable($value) || !is_array($value)) {
            return $value;
        }

        return array_map(static function ($item) {
            return self::cleanAS400Values($item);
        }, $value);
    }

    public static function encodeAS400Values(string $value): string
    {
        return mb_convert_encoding($value, 'UTF-8');
    }
}
