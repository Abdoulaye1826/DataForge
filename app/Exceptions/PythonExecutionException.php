<?php

namespace App\Exceptions;

use Exception;

/**
 * Thrown when a Python script invoked via PythonRunnerService exits with a
 * non-zero status or returns a malformed/error JSON payload.
 */
class PythonExecutionException extends Exception
{
    public function __construct(
        string $message,
        public readonly string $scriptName,
        public readonly ?string $stderr = null,
    ) {
        parent::__construct($message);
    }
}
