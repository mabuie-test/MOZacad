<?php

declare(strict_types=1);

namespace App\Support;

final class UnicodeWordCounter
{
    public static function count(string $text): int
    {
        $tokens = preg_split('/\\PL+/u', $text) ?: [];
        $tokens = array_filter($tokens, static fn (string $token): bool => $token !== '');

        return count($tokens);
    }
}
