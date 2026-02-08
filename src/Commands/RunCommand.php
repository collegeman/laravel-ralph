<?php

namespace Collegeman\Ralph\Commands;

use Illuminate\Console\Command;
use Collegeman\Ralph\GitHub\GitHubSync;
use Collegeman\Ralph\Prd\PrdManager;
use Collegeman\Ralph\Prd\UserStory;
use Collegeman\Ralph\Progress\ProgressLog;
use Collegeman\Ralph\Runner\ClaudeInvoker;
use Collegeman\Ralph\Runner\LoopOrchestrator;
use Collegeman\Ralph\Runner\PromptBuilder;

class RunCommand extends Command
{
    protected $signature = 'ralph:run
        {--max-iterations= : Override max iterations from config}
        {--story= : Target a specific story ID}
        {--dry-run : Show the prompt without invoking Claude}';

    protected $description = 'Run the Ralph autonomous loop';

    public function handle(
        PrdManager $prdManager,
        ProgressLog $progressLog,
        GitHubSync $githubSync,
    ): int {
        if (! $prdManager->exists()) {
            $this->components->error('No prd.json found. Run `php artisan ralph:init` first.');

            return self::FAILURE;
        }

        $maxIterations = (int) ($this->option('max-iterations') ?? config('ralph.max_iterations'));
        $targetStory = $this->option('story');

        if ($this->option('dry-run')) {
            return $this->dryRun($prdManager, $progressLog, $targetStory);
        }

        $this->components->info("Starting Ralph loop (max {$maxIterations} iterations)...");
        $this->newLine();

        $orchestrator = new LoopOrchestrator(
            prdManager: $prdManager,
            progressLog: $progressLog,
            claude: new ClaudeInvoker(config('ralph.claude')),
            promptBuilder: new PromptBuilder(
                config('ralph.gates'),
                config('ralph.completion_signal'),
            ),
            githubSync: $githubSync,
            completionSignal: config('ralph.completion_signal'),
            sleepSeconds: config('ralph.sleep_between_iterations'),
        );

        $orchestrator->onIteration(function (int $iteration, int $max, UserStory $story) {
            $this->components->twoColumnDetail(
                "Iteration {$iteration}/{$max}",
                "<comment>{$story->id}: {$story->title}</comment>",
            );
        });

        $orchestrator->onOutput(function (string $output) {
            $lines = explode("\n", $output);
            $preview = implode("\n", array_slice($lines, 0, 5));

            if (count($lines) > 5) {
                $preview .= "\n... (".(count($lines) - 5).' more lines)';
            }

            $this->line($preview);
            $this->newLine();
        });

        $result = $orchestrator->run($maxIterations, $targetStory);

        $this->newLine();

        if ($result->completed) {
            $this->components->info("All stories complete! ({$result->storiesCompleted}/{$result->storiesTotal})");
        } else {
            $this->components->warn(
                "Loop ended: {$result->reason} ({$result->storiesCompleted}/{$result->storiesTotal} stories complete)"
            );
        }

        $this->components->twoColumnDetail('Iterations run', (string) $result->iterationsRun);

        return $result->completed ? self::SUCCESS : self::FAILURE;
    }

    private function dryRun(PrdManager $prdManager, ProgressLog $progressLog, ?string $targetStory): int
    {
        $prd = $prdManager->load();

        $story = $targetStory
            ? $prd->findStory($targetStory)
            : $prd->nextStory();

        if (! $story) {
            $this->components->info('No incomplete stories found.');

            return self::SUCCESS;
        }

        $promptBuilder = new PromptBuilder(
            config('ralph.gates'),
            config('ralph.completion_signal'),
        );

        $prompt = $promptBuilder->buildIterationPrompt(
            $prd,
            $story,
            $progressLog->read(),
            1,
        );

        $this->components->info("Dry run — prompt for story {$story->id}:");
        $this->newLine();
        $this->line($prompt);

        return self::SUCCESS;
    }
}
