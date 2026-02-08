<?php

namespace Collegeman\Ralph\Runner;

use Closure;
use Collegeman\Ralph\GitHub\GitHubSync;
use Collegeman\Ralph\Prd\PrdManager;
use Collegeman\Ralph\Prd\UserStory;
use Collegeman\Ralph\Progress\ProgressLog;

class LoopOrchestrator
{
    private bool $shouldStop = false;

    private ?Closure $onIteration = null;

    private ?Closure $onOutput = null;

    public function __construct(
        private PrdManager $prdManager,
        private ProgressLog $progressLog,
        private ClaudeInvoker $claude,
        private PromptBuilder $promptBuilder,
        private GitHubSync $githubSync,
        private string $completionSignal,
        private int $sleepSeconds,
    ) {}

    public function onIteration(Closure $callback): self
    {
        $this->onIteration = $callback;

        return $this;
    }

    public function onOutput(Closure $callback): self
    {
        $this->onOutput = $callback;

        return $this;
    }

    public function run(int $maxIterations, ?string $targetStoryId = null): LoopResult
    {
        $this->registerSignalHandlers();
        $this->progressLog->initialize();

        $iterationsRun = 0;

        for ($i = 1; $i <= $maxIterations; $i++) {
            if ($this->shouldStop) {
                return $this->buildResult($iterationsRun, 'Interrupted by signal');
            }

            $prd = $this->prdManager->load();

            $story = $targetStoryId
                ? $prd->findStory($targetStoryId)
                : $prd->nextStory();

            if (! $story) {
                return $this->buildResult($iterationsRun, 'All stories complete');
            }

            if ($targetStoryId && $story->passes) {
                return $this->buildResult($iterationsRun, "Story {$targetStoryId} already passes");
            }

            $this->notifyIteration($i, $maxIterations, $story);

            $result = $this->runIteration($story, $i);
            $iterationsRun++;

            $this->progressLog->append(
                "Iteration {$i} — Story {$result->storyId}\n"
                ."Result: ".($result->successful ? 'Success' : 'In progress')
            );

            if ($result->allComplete) {
                return $this->buildResult($iterationsRun, 'All stories complete — completion signal received');
            }

            if ($targetStoryId && $result->successful) {
                return $this->buildResult($iterationsRun, "Story {$targetStoryId} completed");
            }

            if ($i < $maxIterations) {
                sleep($this->sleepSeconds);
            }
        }

        return $this->buildResult($iterationsRun, 'Max iterations reached');
    }

    private function runIteration(UserStory $story, int $iteration): IterationResult
    {
        $progressContent = $this->progressLog->read();

        $prd = $this->prdManager->load();

        $prompt = $this->promptBuilder->buildIterationPrompt(
            $prd,
            $story,
            $progressContent,
            $iteration,
        );

        $invocation = $this->claude->invoke($prompt);

        $this->notifyOutput($invocation->output);

        $allComplete = $invocation->containsSignal($this->completionSignal);

        if ($this->githubSync->enabled() && $story->issueNumber) {
            $this->githubSync->commentOnIssue(
                $story->issueNumber,
                "Ralph iteration {$iteration}: ".($invocation->successful() ? 'completed' : 'in progress')
            );
        }

        return new IterationResult(
            successful: $invocation->successful(),
            allComplete: $allComplete,
            storyId: $story->id,
            output: $invocation->output,
        );
    }

    public function stop(): void
    {
        $this->shouldStop = true;
    }

    private function registerSignalHandlers(): void
    {
        if (! function_exists('pcntl_signal')) {
            return;
        }

        pcntl_signal(SIGINT, fn () => $this->stop());
        pcntl_signal(SIGTERM, fn () => $this->stop());
    }

    private function buildResult(int $iterationsRun, string $reason): LoopResult
    {
        $prd = $this->prdManager->load();

        return new LoopResult(
            completed: $prd->allComplete(),
            iterationsRun: $iterationsRun,
            storiesCompleted: $prd->completedCount(),
            storiesTotal: $prd->totalCount(),
            reason: $reason,
        );
    }

    private function notifyIteration(int $iteration, int $max, UserStory $story): void
    {
        if ($this->onIteration) {
            ($this->onIteration)($iteration, $max, $story);
        }
    }

    private function notifyOutput(string $output): void
    {
        if ($this->onOutput) {
            ($this->onOutput)($output);
        }
    }
}
