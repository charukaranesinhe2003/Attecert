<?php

declare(strict_types=1);

namespace App\Core;

class ValidationException extends \RuntimeException
{
    public function __construct(private array $errors, private array $input = [])
    {
        parent::__construct('Validation failed');
    }

    public function errors(): array
    {
        return $this->errors;
    }

    public function input(): array
    {
        return $this->input;
    }
}
