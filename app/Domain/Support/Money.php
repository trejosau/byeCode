<?php

namespace App\Domain\Support;

use InvalidArgumentException;

final class Money
{
    public static function cents(mixed $value): int
    {
        if ((! is_string($value) && ! is_int($value) && ! is_float($value))
            || ! preg_match('/^(0|[1-9]\d{0,9})(?:\.(\d{1,2}))?$/D', (string) $value, $m)) {
            throw new InvalidArgumentException('Importe no negativo con máximo dos decimales.');
        }

        return ((int) $m[1] * 100) + (int) str_pad($m[2] ?? '', 2, '0');
    }
}
