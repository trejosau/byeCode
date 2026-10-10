<?php

namespace App\Domain\Support;

use Carbon\CarbonImmutable;
use InvalidArgumentException;

final class Utc
{
    public static function parse(mixed $value): CarbonImmutable
    {
        if (! is_string($value) || ! preg_match('/^(\d{4})-(\d{2})-(\d{2})T(\d{2}):(\d{2}):(\d{2})(?:\.(\d{1,6}))?Z$/D', $value, $m)
            || ! checkdate((int) $m[2], (int) $m[3], (int) $m[1])
            || (int) $m[4] > 23 || (int) $m[5] > 59 || (int) $m[6] > 59) {
            throw new InvalidArgumentException('Se requiere una fecha UTC válida, por ejemplo 2026-10-09T16:00:00Z.');
        }

        return CarbonImmutable::parse($value, 'UTC');
    }

    public static function iso(CarbonImmutable $date): string
    {
        return $date->utc()->format('Y-m-d\TH:i:s.u\Z');
    }
}
