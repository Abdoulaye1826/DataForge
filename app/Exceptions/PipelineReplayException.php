<?php

namespace App\Exceptions;

use Exception;

/**
 * Thrown when replaying a table's recorded pipeline steps onto a new table
 * fails partway through - e.g. the new dataset is missing a column an
 * earlier step relied on. Replay stops at the first failure rather than
 * silently skipping steps, since later steps usually depend on it.
 */
class PipelineReplayException extends Exception
{
}
