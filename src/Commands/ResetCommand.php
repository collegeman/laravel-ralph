<?php

namespace Collegeman\Ralph\Commands;

use Illuminate\Console\Command;
use Collegeman\Ralph\Prd\PrdManager;
use Collegeman\Ralph\Progress\ProgressLog;

class ResetCommand extends Command
{
    protected $signature = 'ralph:reset
        {--progress : Clear progress.txt}
        {--stories : Reset all story passes to false}
        {--all : Reset everything}';

    protected $description = 'Reset Ralph loop state for a fresh run';

    public function handle(PrdManager $prdManager, ProgressLog $progressLog): int
    {
        $resetProgress = $this->option('progress') || $this->option('all');
        $resetStories = $this->option('stories') || $this->option('all');

        if (! $resetProgress && ! $resetStories) {
            $this->components->error('Specify --progress, --stories, or --all.');

            return self::FAILURE;
        }

        $actions = [];

        if ($resetProgress) {
            $actions[] = 'Clear progress.txt';
        }

        if ($resetStories) {
            $actions[] = 'Reset all story statuses to "not passing"';
        }

        $this->components->warn('This will:');

        foreach ($actions as $action) {
            $this->line("  - {$action}");
        }

        if (! $this->confirm('Continue?')) {
            $this->components->info('Cancelled.');

            return self::SUCCESS;
        }

        if ($resetProgress) {
            $progressLog->clear();
            $this->components->task('Cleared progress.txt');
        }

        if ($resetStories) {
            $prdManager->resetStories();
            $this->components->task('Reset all story statuses');
        }

        $this->components->info('Reset complete.');

        return self::SUCCESS;
    }
}
