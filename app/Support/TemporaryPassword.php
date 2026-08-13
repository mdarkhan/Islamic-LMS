<?php

namespace App\Support;

use Illuminate\Support\Str;

/**
 * A short, dictatable temporary password. Ambiguous characters (0/O, 1/l/I) are
 * excluded so it can be read aloud or written down for a student without confusion.
 * The plaintext is returned to the caller once for delivery and never persisted.
 */
class TemporaryPassword
{
    private const ALPHABET = 'abcdefghjkmnpqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ23456789';

    public static function generate(int $length = 8): string
    {
        $out = '';
        $max = strlen(self::ALPHABET) - 1;

        for ($i = 0; $i < $length; $i++) {
            $out .= self::ALPHABET[random_int(0, $max)];
        }

        // Guard against an all-letters string with no digit.
        return preg_match('/\d/', $out) ? $out : substr($out, 0, -1).random_int(2, 9);
    }
}
