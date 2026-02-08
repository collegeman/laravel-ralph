<?php

namespace Snarktank\Ralph\Runner;

class LoopResult
{
    public function __construct(
        public readonly bool $completed,
        public readonly int $iterationsRun,
        public readonly int $storiesCompleted,
        public readonly int $storiesTotal,
        public readonly string $reason,
    ) {}
}
