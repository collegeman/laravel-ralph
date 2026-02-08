<?php

namespace Snarktank\Ralph\Commands;

use Illuminate\Console\Command;
use Snarktank\Ralph\Prd\PrdManager;
use Snarktank\Ralph\Prd\UserStory;

class StatusCommand extends Command
{
    protected $signature = 'ralph:status';

    protected $description = 'Display current PRD progress';

    public function handle(PrdManager $prdManager): int
    {
        if (! $prdManager->exists()) {
            $this->components->error('No prd.json found. Run `php artisan ralph:init` first.');

            return self::FAILURE;
        }

        $prd = $prdManager->load();

        $this->components->info("{$prd->project}: {$prd->description}");

        if (! empty($prd->branchName)) {
            $this->components->twoColumnDetail('Branch', $prd->branchName);
        }

        $this->newLine();

        $rows = array_map(fn (UserStory $story) => [
            $story->id,
            $story->title,
            $story->priority,
            $this->formatStatus($story->passes),
            $story->issueNumber ? "#{$story->issueNumber}" : '',
        ], $prd->userStories);

        $this->table(
            ['ID', 'Title', 'Priority', 'Status', 'Issue'],
            $rows,
        );

        $completed = $prd->completedCount();
        $total = $prd->totalCount();
        $percentage = $total > 0 ? round(($completed / $total) * 100) : 0;

        $this->newLine();
        $this->components->twoColumnDetail(
            'Progress',
            "{$completed}/{$total} stories complete ({$percentage}%)",
        );

        return self::SUCCESS;
    }

    private function formatStatus(bool $passes): string
    {
        return $passes ? '<info>Pass</info>' : '<comment>Todo</comment>';
    }
}
