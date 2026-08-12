<?php

namespace App\Services\Import;

/**
 * Thrown to unwind a preview transaction. Never an error condition — importers
 * catch it so a dry run can exercise the real write path and then roll back.
 */
class DryRunComplete extends \RuntimeException {}
