<?php

namespace App\Services\Quiz;

use RuntimeException;

/**
 * Raised when an autosave payload contains an option outside the addressed question.
 * The service fails closed rather than silently repairing the selection.
 */
class InvalidAnswerSelectionException extends RuntimeException {}
