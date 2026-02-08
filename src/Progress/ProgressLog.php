<?php

namespace Collegeman\Ralph\Progress;

class ProgressLog
{
    public function __construct(
        private string $path,
    ) {}

    public function exists(): bool
    {
        return file_exists($this->path);
    }

    public function initialize(): void
    {
        if ($this->exists()) {
            return;
        }

        $header = "# Ralph Progress Log\n";
        $header .= '# Created: '.date('Y-m-d H:i:s')."\n";
        $header .= "# This file is append-only. Do not delete entries.\n\n";

        file_put_contents($this->path, $header);
    }

    public function append(string $entry): void
    {
        $timestamp = date('Y-m-d H:i:s');
        $formatted = "\n---\n## [{$timestamp}]\n\n{$entry}\n";

        file_put_contents($this->path, $formatted, FILE_APPEND);
    }

    public function read(): string
    {
        if (! $this->exists()) {
            return '';
        }

        return file_get_contents($this->path);
    }

    public function clear(): void
    {
        if ($this->exists()) {
            unlink($this->path);
        }
    }

    public function getPath(): string
    {
        return $this->path;
    }
}
