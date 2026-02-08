<?php

namespace Snarktank\Ralph\Runner;

use Illuminate\Support\Facades\Process;
use RuntimeException;

class ClaudeInvoker
{
    private string $binary;

    private ?string $model;

    private ?int $maxTurns;

    private string $permissionMode;

    public function __construct(array $config)
    {
        $this->binary = $config['binary'] ?? 'claude';
        $this->model = $config['model'] ?? null;
        $this->maxTurns = $config['max_turns'] ?? null;
        $this->permissionMode = $config['permission_mode'] ?? 'bypassPermissions';
    }

    public function invoke(string $prompt): InvocationResult
    {
        $command = $this->buildCommand();

        $result = Process::timeout(0)
            ->input($prompt)
            ->run($command);

        return new InvocationResult(
            output: $result->output(),
            errorOutput: $result->errorOutput(),
            exitCode: $result->exitCode(),
        );
    }

    public function passthrough(string $prompt): int
    {
        $command = $this->buildInteractiveCommand($prompt);

        $descriptors = [
            0 => STDIN,
            1 => STDOUT,
            2 => STDERR,
        ];

        $process = proc_open($command, $descriptors, $pipes);

        if (! is_resource($process)) {
            throw new RuntimeException('Failed to start Claude Code process');
        }

        return proc_close($process);
    }

    private function buildCommand(): string
    {
        $parts = [
            escapeshellarg($this->binary),
            '--print',
            '--permission-mode',
            escapeshellarg($this->permissionMode),
        ];

        if ($this->model) {
            $parts[] = '--model';
            $parts[] = escapeshellarg($this->model);
        }

        if ($this->maxTurns) {
            $parts[] = '--max-turns';
            $parts[] = escapeshellarg((string) $this->maxTurns);
        }

        return implode(' ', $parts);
    }

    private function buildInteractiveCommand(string $prompt): string
    {
        $parts = [
            escapeshellarg($this->binary),
            '--permission-mode',
            escapeshellarg($this->permissionMode),
            '-p',
            escapeshellarg($prompt),
        ];

        if ($this->model) {
            $parts[] = '--model';
            $parts[] = escapeshellarg($this->model);
        }

        if ($this->maxTurns) {
            $parts[] = '--max-turns';
            $parts[] = escapeshellarg((string) $this->maxTurns);
        }

        return implode(' ', $parts);
    }
}
