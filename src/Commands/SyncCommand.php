<?php

namespace Collegeman\Ralph\Commands;

use Illuminate\Console\Command;
use Collegeman\Ralph\GitHub\GitHubSync;
use Collegeman\Ralph\Prd\PrdManager;
use Collegeman\Ralph\Runner\ClaudeInvoker;
use Collegeman\Ralph\Runner\PromptBuilder;

class SyncCommand extends Command
{
    protected $signature = 'ralph:sync
        {--pull : Pull new GitHub issues into prd.json}
        {--both : Full bidirectional sync}';

    protected $description = 'Sync story status with GitHub Issues';

    public function handle(
        PrdManager $prdManager,
        GitHubSync $githubSync,
    ): int {
        if (! $prdManager->exists()) {
            $this->components->error('No prd.json found. Run `php artisan ralph:init` first.');

            return self::FAILURE;
        }

        $doPush = ! $this->option('pull') || $this->option('both');
        $doPull = $this->option('pull') || $this->option('both');

        if ($doPush) {
            $this->pushStatus($prdManager, $githubSync);
        }

        if ($doPull) {
            $this->pullIssues($prdManager, $githubSync);
        }

        return self::SUCCESS;
    }

    private function pushStatus(PrdManager $prdManager, GitHubSync $githubSync): void
    {
        $prd = $prdManager->load();
        $synced = 0;

        foreach ($prd->userStories as $story) {
            if (! $story->issueNumber) {
                continue;
            }

            $this->components->task(
                "Syncing {$story->id} → #{$story->issueNumber}",
                fn () => $githubSync->pushStatus($story)
            );
            $synced++;
        }

        if ($synced === 0) {
            $this->components->warn('No stories linked to GitHub issues.');

            return;
        }

        $this->components->info("Pushed status for {$synced} stories.");
    }

    private function pullIssues(PrdManager $prdManager, GitHubSync $githubSync): void
    {
        $label = config('ralph.github.label');
        $this->components->info("Pulling issues with label: {$label}");

        $issues = $githubSync->pullIssues();
        $prd = $prdManager->load();

        $newIssues = array_filter($issues, function ($issue) use ($prd) {
            return $prd->findByIssueNumber($issue['number']) === null;
        });

        if (empty($newIssues)) {
            $this->components->info('No new issues to import.');

            return;
        }

        $this->components->info('Found '.count($newIssues).' new issues. Converting...');

        $promptBuilder = new PromptBuilder(
            config('ralph.gates'),
            config('ralph.completion_signal'),
        );

        $prompt = $promptBuilder->buildIssueConversionPrompt(array_values($newIssues));

        $claude = new ClaudeInvoker(config('ralph.claude'));
        $result = $claude->invoke($prompt);

        if (! $result->successful()) {
            $this->components->error('Failed to convert issues:');
            $this->line($result->errorOutput);

            return;
        }

        $json = trim($result->output);

        if (preg_match('/```(?:json)?\s*\n(.*?)\n```/s', $json, $matches)) {
            $json = $matches[1];
        }

        $data = json_decode($json, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->components->error('Failed to parse JSON from Claude output.');
            $this->line($json);

            return;
        }

        $stories = $data['userStories'] ?? [];

        $existingCount = $prd->totalCount();

        foreach ($stories as $storyData) {
            $storyData['priority'] = $existingCount + ($storyData['priority'] ?? 1);
            $prdManager->addStory(\Collegeman\Ralph\Prd\UserStory::fromArray($storyData));
            $existingCount++;
        }

        $this->components->info('Imported '.count($stories).' stories from GitHub issues.');
    }
}
