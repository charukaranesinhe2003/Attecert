<?php

declare(strict_types=1);

namespace App\Mail;

use App\Core\View;

final class Template
{
    public static function wrap(string $inner, array $company): string
    {
        return View::render('emails/layout', [
            'body' => $inner,
            'company' => $company,
        ]);
    }
}
