<?php

namespace App\Services\Points;

use RuntimeException;

class InsufficientPointsException extends RuntimeException
{
    public function __construct(
        public readonly int $required,
        public readonly int $available,
    ) {
        parent::__construct("Insufficient points: need {$required}, have {$available}.");
    }
}
