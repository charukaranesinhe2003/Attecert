<?php

declare(strict_types=1);

namespace App\Helpers;

final class Password
{
    public static function hash(string $password): string
    {
        if (defined('PASSWORD_ARGON2ID') && in_array('argon2id', password_algos(), true)) {
            return password_hash($password, PASSWORD_ARGON2ID);
        }
        // Bcrypt must not silently discard the tail of an accepted password.
        if (strlen($password) > 72) {
            throw new \App\Core\ValidationException(['password' => 'This PHP installation supports passwords of at most 72 bytes. Use a shorter password or enable Argon2id.']);
        }

        return password_hash($password, PASSWORD_DEFAULT);
    }
}
