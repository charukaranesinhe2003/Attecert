<?php

declare(strict_types=1);

namespace App\Helpers;

final class Money
{
    public static function add(string|float|int $left, string|float|int $right): string
    {
        return bcadd(self::norm($left), self::norm($right), 2);
    }

    public static function sub(string|float|int $left, string|float|int $right): string
    {
        return bcsub(self::norm($left), self::norm($right), 2);
    }

    public static function mul(string|float|int $left, string|float|int $right): string
    {
        return bcmul(self::norm($left), self::norm($right), 2);
    }

    public static function percent(string|float|int $amount, string|float|int $rate): string
    {
        return bcmul(self::norm($amount), bcdiv(self::norm($rate), '100', 6), 2);
    }

    public static function cmp(string|float|int $left, string|float|int $right): int
    {
        return bccomp(self::norm($left), self::norm($right), 2);
    }

    public static function norm(string|float|int $value): string
    {
        $string = is_string($value) ? trim($value) : (string) $value;
        if ($string === '' || !is_numeric($string)) {
            return '0.00';
        }

        return bcadd($string, '0', 2);
    }
}
