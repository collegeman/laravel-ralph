<?php

namespace Collegeman\Ralph\Commands;

use Illuminate\Console\Command;
use Collegeman\Ralph\GitHub\GitHubSync;
use Collegeman\Ralph\Prd\PrdManager;
use Collegeman\Ralph\Runner\ClaudeInvoker;
use Collegeman\Ralph\Runner\PromptBuilder;

class PrdCommand extends Command
{
    protected $signature = 'ralph:prd
        {--from= : Path to a markdown requirements file}
        {--from-issues : Import stories from GitHub Issues}
        {--label= : GitHub issue label filter (default: from config)}';

    protected $description = 'Create or update prd.json from various sources';

    public function handle(
        PrdManager $prdManager,
        GitHubSync $githubSync,
    ): int {
        if ($this->option('from-issues')) {
            return $this->fromGitHubIssues($prdManager, $githubSync);
        }

        if ($this->option('from')) {
            return $this->fromFile($prdManager);
        }

        return $this->interactive($prdManager);
    }

    private function interactive(PrdManager $prdManager): int
    {
        $this->components->info('Starting interactive PRD session with Claude...');
        $this->line('Describe what you want to build. Claude will generate prd.json.');
        $this->newLine();

        $description = $this->ask('What do you want to build?');

        if (! $description) {
            $this->components->error('No description provided.');

            return self::FAILURE;
        }

        $promptBuilder = $this->makePromptBuilder();
        $prompt = $promptBuilder->buildPrdGenerationPrompt($description, 'interactive');

        $claude = $this->makeClaude();
        $this->components->info('Generating PRD...');

        $result = $claude->invoke($prompt);

        if (! $result->successful()) {
            $this->components->error('Claude invocation failed:');
            $this->line($result->errorOutput);

            return self::FAILURE;
        }

        return $this->savePrdFromOutput($prdManager, $result->output);
    }

    private function fromFile(PrdManager $prdManager): int
    {
        $path = $this->option('from');

        if (! file_exists($path)) {
            $this->components->error("File not found: {$path}");

            return self::FAILURE;
        }

        $this->components->info("Generating PRD from {$path}...");

        $content = file_get_contents($path);
        $promptBuilder = $this->makePromptBuilder();
        $prompt = $promptBuilder->buildPrdGenerationPrompt($content, 'file');

        $claude = $this->makeClaude();
        $result = $claude->invoke($prompt);

        if (! $result->successful()) {
            $this->components->error('Claude invocation failed:');
            $this->line($result->errorOutput);

            return self::FAILURE;
        }

        return $this->savePrdFromOutput($prdManager, $result->output);
    }

    private function fromGitHubIssues(PrdManager $prdManager, GitHubSync $githubSync): int
    {
        $label = $this->option('label') ?? config('ralph.github.label');

        $this->components->info("Fetching GitHub issues with label: {$label}");

        $issues = $githubSync->pullIssues($label);

        if (empty($issues)) {
            $this->components->warn("No open issues found with label '{$label}'.");

            return self::SUCCESS;
        }

        $this->components->info('Found '.count($issues).' issues. Converting to PRD...');

        $promptBuilder = $this->makePromptBuilder();
        $prompt = $promptBuilder->buildIssueConversionPrompt($issues);

        $claude = $this->makeClaude();
        $result = $claude->invoke($prompt);

        if (! $result->successful()) {
            $this->components->error('Claude invocation failed:');
            $this->line($result->errorOutput);

            return self::FAILURE;
        }

        return $this->savePrdFromOutput($prdManager, $result->output);
    }

    private function savePrdFromOutput(PrdManager $prdManager, string $output): int
    {
        $json = $this->extractJson($output);

        if (! $json) {
            $this->components->error('Could not extract valid JSON from Claude output:');
            $this->line($output);

            return self::FAILURE;
        }

        $prdManager->saveRaw($json);

        $prd = $prdManager->load();
        $this->components->info("PRD saved with {$prd->totalCount()} stories.");

        return self::SUCCESS;
    }

    private function extractJson(string $output): ?string
    {
        if (preg_match('/```(?:json)?\s*\n(.*?)\n```/s', $output, $matches)) {
            $output = $matches[1];
        }

        $output = trim($output);

        $decoded = json_decode($output, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return null;
        }

        return $output;
    }

    private function makeClaude(): ClaudeInvoker
    {
        return new ClaudeInvoker(config('ralph.claude'));
    }

    private function makePromptBuilder(): PromptBuilder
    {
        return new PromptBuilder(
            config('ralph.gates'),
            config('ralph.completion_signal'),
        );
    }
}
