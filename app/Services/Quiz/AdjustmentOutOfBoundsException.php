<?php

namespace App\Services\Quiz;

/**
 * Thrown when a manual adjustment would push final_score outside 0..total_marks. We
 * validate rather than silently clamp, so the admin sees exactly why it was refused
 * (brief §31).
 */
class AdjustmentOutOfBoundsException extends \RuntimeException {}
