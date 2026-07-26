<?php

namespace App\Services\Python;

use App\DTO\PythonScriptResult;
use App\Exceptions\PythonExecutionException;
use App\Models\PythonExecution;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

/**
 * Single entry point for every Laravel -> Python call in DataForge.
 *
 * Every script under python/scripts/{$script} is invoked as:
 *   {PYTHON_BIN} {script}.py --input <tmp_input.json> --output <tmp_output.json>
 *
 * and is expected to write a JSON object shaped like:
 *   {"success": true, "data": {...}}
 *   {"success": false, "error": "human readable message"}
 *
 * to its --output file. Input/output payloads always go through temp files
 * (never CLI arguments) so large datasets/params never hit shell length
 * limits or quoting issues, and the Process command is always built as an
 * argument array — never a concatenated string — to rule out command
 * injection regardless of what the payload contains.
 */
class PythonRunnerService
{
    /**
     * Run a Python script and return its decoded result.
     *
     * @param string $script Script filename, e.g. "smoke_test.py".
     * @param array $payload Arbitrary JSON-serializable input for the script.
     * @param int|null $projectId Project this call belongs to, purely for the
     *        python_executions audit trail (nullable - Phase 0's smoke test
     *        has no project).
     *
     * @throws PythonExecutionException When the process fails or the script
     *         reports {"success": false}.
     */
    public function run(string $script, array $payload = [], ?int $projectId = null): PythonScriptResult
    {
        $scriptPath = config('dataforge.python.scripts_path') . DIRECTORY_SEPARATOR . $script;

        if (! File::exists($scriptPath)) {
            throw new PythonExecutionException("Python script not found: {$script}", $script);
        }

        $runId = (string) Str::uuid();
        $tmpDir = config('dataforge.python.tmp_path') . DIRECTORY_SEPARATOR . $runId;
        File::ensureDirectoryExists($tmpDir);

        $inputPath = $tmpDir . DIRECTORY_SEPARATOR . 'input.json';
        $outputPath = $tmpDir . DIRECTORY_SEPARATOR . 'output.json';

        File::put($inputPath, json_encode($payload, JSON_THROW_ON_ERROR));

        $process = new Process([
            config('dataforge.python.bin'),
            $scriptPath,
            '--input', $inputPath,
            '--output', $outputPath,
        ]);
        $process->setTimeout((int) config('dataforge.python.timeout'));

        if (DIRECTORY_SEPARATOR === '\\') {
            // The web server's own PATH can be polluted by dev-tool shells
            // (Git Bash/MSYS2 prepend their own DLL directories ahead of
            // System32), which makes native Win32 processes like python.exe
            // fail to initialize Winsock (WinError 10106) because the wrong
            // system DLLs get picked up first. Force System32 first for the
            // child process; setEnv() only overrides PATH; everything else
            // (SystemRoot, TEMP, ...) is still inherited from the parent.
            $systemRoot = getenv('SystemRoot') ?: 'C:\\Windows';
            $pythonDir = dirname(config('dataforge.python.bin'));

            $process->setEnv([
                'PATH' => implode(';', [
                    $systemRoot . '\\System32',
                    $systemRoot,
                    $systemRoot . '\\System32\\Wbem',
                    $pythonDir,
                    getenv('PATH') ?: '',
                ]),
            ]);
        }

        $startedAt = microtime(true);

        try {
            $process->run();
            $durationMs = (int) round((microtime(true) - $startedAt) * 1000);

            if (! $process->isSuccessful()) {
                throw new ProcessFailedException($process);
            }

            $rawOutput = File::exists($outputPath) ? File::get($outputPath) : null;

            if ($rawOutput === null) {
                throw new PythonExecutionException(
                    "Script {$script} exited successfully but produced no output file.",
                    $script,
                    $process->getErrorOutput(),
                );
            }

            $decoded = json_decode($rawOutput, true);

            if (! is_array($decoded)) {
                throw new PythonExecutionException(
                    "Script {$script} produced invalid JSON output.",
                    $script,
                    $process->getErrorOutput(),
                );
            }

            $result = PythonScriptResult::fromDecodedPayload($decoded, $durationMs);

            $this->logExecution($projectId, $script, $payload, $decoded, $result->success ? 'success' : 'error', $durationMs, $result->error);

            if (! $result->success) {
                throw new PythonExecutionException(
                    $result->error ?? "Script {$script} reported failure without a message.",
                    $script,
                );
            }

            return $result;
        } catch (ProcessFailedException $e) {
            $durationMs = (int) round((microtime(true) - $startedAt) * 1000);
            $stderr = $process->getErrorOutput();

            $this->logExecution($projectId, $script, $payload, null, 'error', $durationMs, $stderr);

            throw new PythonExecutionException(
                "Script {$script} failed (exit code {$process->getExitCode()}).",
                $script,
                $stderr,
            );
        } finally {
            File::deleteDirectory($tmpDir);
        }
    }

    private function logExecution(
        ?int $projectId,
        string $script,
        array $inputPayload,
        ?array $outputPayload,
        string $status,
        int $durationMs,
        ?string $errorMessage,
    ): void {
        PythonExecution::create([
            'project_id' => $projectId,
            'script_name' => $script,
            'input_payload' => $inputPayload,
            'output_payload' => $outputPayload,
            'status' => $status,
            'duration_ms' => $durationMs,
            'error_message' => $this->sanitizeForStorage($errorMessage),
        ]);
    }

    /**
     * Windows OS-level process errors (e.g. "executable not found") are
     * reported in the console's OEM codepage, not UTF-8 - storing them
     * as-is would make the insert fail on a utf8mb4 column. mb_scrub()
     * replaces invalid byte sequences instead of letting the query blow up.
     */
    private function sanitizeForStorage(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        return mb_scrub($value, 'UTF-8');
    }
}
