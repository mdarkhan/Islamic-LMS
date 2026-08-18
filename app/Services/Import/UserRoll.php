<?php

namespace App\Services\Import;

use App\Models\User;

/** Small dependency boundary so legacy parsers share the application's canonical roll rule. */
class UserRoll
{
    public static function normalise(?string $roll): ?string
    {
        return User::normaliseRoll($roll);
    }
}
