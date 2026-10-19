<?php

declare(strict_types=1);

namespace App\Core;

use InvalidArgumentException;

final class Password
{
    public static function assertStrong(string $password): void
    {
        $length = strlen($password);
        if ($length < 10 || $length > 200 || !preg_match('/[A-Za-z]/', $password) || !preg_match('/\d/', $password)) {
            throw new InvalidArgumentException('A senha precisa ter de 10 a 200 caracteres, com letras e números.');
        }
    }
}
