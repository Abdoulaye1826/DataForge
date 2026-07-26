<?php

namespace App\DTO;

/**
 * Decoded result of a Python script execution, as produced by
 * PythonRunnerService::run(). Every script under python/scripts/ is expected
 * to write a JSON object shaped like {"success": bool, "data": {...}} or
 * {"success": false, "error": "..."} to its --output file.
 */
final class PythonScriptResult
{
    public function __construct(
        public readonly bool $success,
        public readonly array $data,
        public readonly ?string $error,
        public readonly int $durationMs,
    ) {
    }

    public static function fromDecodedPayload(array $payload, int $durationMs): self
    {
        return new self(
            success: (bool) ($payload['success'] ?? false),
            data: $payload['data'] ?? [],
            error: $payload['error'] ?? null,
            durationMs: $durationMs,
        );
    }
}
