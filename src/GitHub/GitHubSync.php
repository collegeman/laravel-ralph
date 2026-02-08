<?php

namespace Collegeman\Ralph\GitHub;

use Illuminate\Support\Facades\Process;
use RuntimeException;
use Collegeman\Ralph\Prd\UserStory;

class GitHubSync
{
    private bool $isEnabled;

    private string $label;

    private bool $closeOnPass;

    private bool $commentOnProgress;

    public function __construct(array $config)
    {
        $this->isEnabled = $config['enabled'] ?? false;
        $this->label = $config['label'] ?? 'ralph';
        $this->closeOnPass = $config['close_on_pass'] ?? true;
        $this->commentOnProgress = $config['comment_on_progress'] ?? true;
    }

    public function enabled(): bool
    {
        return $this->isEnabled;
    }

    public function pullIssues(?string $labelOverride = null): array
    {
        $this->ensureGhAvailable();

        $label = $labelOverride ?? $this->label;

        $result = Process::run(
            "gh issue list --label ".escapeshellarg($label)." --state open --json number,title,body,labels --limit 100"
        );

        if (! $result->successful()) {
            throw new RuntimeException('Failed to fetch GitHub issues: '.$result->errorOutput());
        }

        return json_decode($result->output(), true, 512, JSON_THROW_ON_ERROR);
    }

    public function pushStatus(UserStory $story): void
    {
        if (! $story->issueNumber) {
            return;
        }

        $this->ensureGhAvailable();

        if ($story->passes && $this->closeOnPass) {
            $this->closeIssue($story->issueNumber);
            $this->commentOnIssue(
                $story->issueNumber,
                "Ralph: Story {$story->id} ({$story->title}) — all acceptance criteria passing. Closing."
            );

            return;
        }

        if ($this->commentOnProgress) {
            $status = $story->passes ? 'passing' : 'in progress';
            $this->commentOnIssue(
                $story->issueNumber,
                "Ralph status update: Story {$story->id} is {$status}."
            );
        }
    }

    public function commentOnIssue(int $number, string $body): void
    {
        if (! $this->commentOnProgress) {
            return;
        }

        $this->ensureGhAvailable();

        $result = Process::run(
            "gh issue comment ".escapeshellarg((string) $number)." --body ".escapeshellarg($body)
        );

        if (! $result->successful()) {
            throw new RuntimeException("Failed to comment on issue #{$number}: ".$result->errorOutput());
        }
    }

    public function closeIssue(int $number): void
    {
        $this->ensureGhAvailable();

        $result = Process::run(
            "gh issue close ".escapeshellarg((string) $number)
        );

        if (! $result->successful()) {
            throw new RuntimeException("Failed to close issue #{$number}: ".$result->errorOutput());
        }
    }

    private function ensureGhAvailable(): void
    {
        $result = Process::run('which gh');

        if (! $result->successful()) {
            throw new RuntimeException(
                'The gh CLI is not installed. Install it from https://cli.github.com and run `gh auth login`.'
            );
        }
    }
}
