<?php

declare(strict_types=1);

namespace App\Helpers;

use App\Core\ValidationException;

/** Validate the existing two-decimal database representation before BCMath receives input. */
final class Decimal
{
    public static function amount(mixed $value, string $field = 'amount', string $maximum = '9999999999.99', string $minimum = '0.00'): string
    {
        if ((!is_string($value) && !is_int($value) && !is_float($value))
            || strlen((string) $value) > 32
            || !preg_match('/^(?:\d+(?:\.\d{1,2})?|\.\d{1,2})$/D', trim((string) $value))) {
            throw new ValidationException([$field => __('admin_validation.decimal')]);
        }
        $amount = Money::norm($value);
        if (Money::cmp($amount, $minimum) < 0 || Money::cmp($amount, $maximum) > 0) {
            throw new ValidationException([$field => __('admin_validation.decimal_range', ['min' => $minimum, 'max' => $maximum])]);
        }

        return $amount;
    }

    public static function quantity(mixed $value, string $field = 'quantity'): string
    {
        return self::amount($value, $field, '999999.99', '0.01');
    }
}
