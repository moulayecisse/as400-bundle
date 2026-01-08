<?php

namespace Cisse\Bundle\As400\Utility;

class As400Utility
{
    /**
     * Clean AS400 values recursively (for arrays of rows).
     * Optimized to reduce function call overhead.
     */
    public static function cleanAS400Values(mixed $value): mixed
    {
        if (is_string($value)) {
            $trimmed = trim($value);
            // Only convert encoding if needed (avoid unnecessary mb_convert_encoding calls)
            return self::needsEncoding($trimmed) ? mb_convert_encoding($trimmed, 'UTF-8') : $trimmed;
        }

        if (!is_array($value)) {
            return $value;
        }

        // Optimize for array of rows (most common case)
        return self::cleanAS400Array($value);
    }

    /**
     * Clean a single row from AS400 (optimized for single-row processing in generators).
     *
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    public static function cleanAS400Row(array $row): array
    {
        foreach ($row as $key => $value) {
            if (is_string($value)) {
                $trimmed = trim($value);
                $row[$key] = self::needsEncoding($trimmed) ? mb_convert_encoding($trimmed, 'UTF-8') : $trimmed;
            }
        }
        return $row;
    }

    /**
     * Clean an array of AS400 values efficiently.
     *
     * @param array<mixed> $array
     * @return array<mixed>
     */
    private static function cleanAS400Array(array $array): array
    {
        // Check if this is an array of rows (associative arrays)
        $firstKey = array_key_first($array);
        if ($firstKey !== null && is_array($array[$firstKey])) {
            // Array of rows - process each row
            foreach ($array as $rowKey => $row) {
                if (is_array($row)) {
                    $array[$rowKey] = self::cleanAS400Row($row);
                }
            }
            return $array;
        }

        // Single row or mixed array - process each value
        foreach ($array as $key => $value) {
            if (is_string($value)) {
                $trimmed = trim($value);
                $array[$key] = self::needsEncoding($trimmed) ? mb_convert_encoding($trimmed, 'UTF-8') : $trimmed;
            } elseif (is_array($value)) {
                $array[$key] = self::cleanAS400Array($value);
            }
        }

        return $array;
    }

    /**
     * Check if a string needs encoding conversion.
     * AS400 typically returns ISO-8859-1 or EBCDIC data.
     */
    private static function needsEncoding(string $value): bool
    {
        if ($value === '') {
            return false;
        }

        // Quick check: if already valid UTF-8, no conversion needed
        return !mb_check_encoding($value, 'UTF-8');
    }

    /**
     * @deprecated Use cleanAS400Values() instead
     */
    public static function encodeAS400Values(string $value): string
    {
        return mb_convert_encoding($value, 'UTF-8');
    }
}
