<?php

namespace App\Services\Quiz;

use RuntimeException;

/**
 * Raised when an autosave payload is malformed — a foreign option id, or more than
 * one option for a single-choice question. The service fails closed rather than
 * silently repairing the selection.
 */
class InvalidAnswerSelectionException extends RuntimeException {}
