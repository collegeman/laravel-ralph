<?php

namespace Snarktank\Ralph\Runner;

class InvocationResult
{
    public function __construct(
        public readonly string $output,
        public readonly string $errorOutput,
        public readonly int $exitCode,
    ) {}

    public function successful(): bool
    {
        return $this->exitCode === 0;
    }

    public function containsSignal(string $signal): bool
    {
        return str_contains($this->output, $signal);
    }
}
