<?php

namespace Collegeman\Ralph\Runner;

class IterationResult
{
    public function __construct(
        public readonly bool $successful,
        public readonly bool $allComplete,
        public readonly string $storyId,
        public readonly string $output,
    ) {}
}
