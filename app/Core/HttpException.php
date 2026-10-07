<?php

declare(strict_types=1);

namespace App\Core;

class HttpException extends \RuntimeException
{
    public function __construct(
        private int $status,
        string $message = '',
        private bool $json = false
    ) {
        parent::__construct($message !== '' ? $message : 'HTTP ' . $status);
    }

    public function status(): int
    {
        return $this->status;
    }
}
