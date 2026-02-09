<?php

namespace Collegeman\Ralph\Runner;

use Collegeman\Ralph\Prd\Prd;
use Collegeman\Ralph\Prd\UserStory;

class PromptBuilder
{
    public function __construct(
        private array $gates,
        private string $completionSignal,
    ) {}

    public function buildIterationPrompt(Prd $prd, UserStory $story, string $progressContent, int $iteration): string
    {
        $sections = [
            $this->buildPreamble(),
            $this->buildPrdSection($prd),
            $this->buildStorySection($story, $iteration),
            $this->buildGatesSection(),
            $this->buildProgressSection($progressContent),
            $this->buildInstructions($story),
        ];

        return implode("\n\n", array_filter($sections));
    }

    public function buildPrdGenerationPrompt(string $input, string $mode): string
    {
        $schema = $this->getPrdSchema();

        if ($mode === 'file') {
            return <<<PROMPT
            You are helping create a PRD (Product Requirements Document) for a Laravel project.

            Convert the following requirements into a prd.json file. Each user story must:
            - Be completable in a single AI coding session
            - Have verifiable acceptance criteria (not vague like "works correctly")
            - Include "PHPStan passes" and "Tests pass" in acceptance criteria
            - Be ordered by dependency (earlier stories don't depend on later ones)

            Output ONLY the JSON, no other text.

            Schema:
            {$schema}

            Requirements:
            {$input}
            PROMPT;
        }

        return <<<PROMPT
        You are helping create a PRD (Product Requirements Document) for a Laravel project.

        The user will describe what they want to build. Ask clarifying questions if needed,
        then generate a prd.json file. Each user story must:
        - Be completable in a single AI coding session
        - Have verifiable acceptance criteria
        - Include "PHPStan passes" and "Tests pass" in acceptance criteria
        - Be ordered by dependency

        Output ONLY the JSON when ready.

        Schema:
        {$schema}

        User's description:
        {$input}
        PROMPT;
    }

    public function buildIssueConversionPrompt(array $issues): string
    {
        $schema = $this->getPrdSchema();
        $issuesJson = json_encode($issues, JSON_PRETTY_PRINT);

        return <<<PROMPT
        Convert these GitHub issues into a prd.json file for a Laravel project.

        Each issue becomes a user story. Derive acceptance criteria from the issue body.
        Always include "PHPStan passes" and "Tests pass" in acceptance criteria.
        Preserve the issue number in the "issueNumber" field.
        Set priority based on issue labels and order.

        Output ONLY the JSON, no other text.

        Schema:
        {$schema}

        GitHub Issues:
        {$issuesJson}
        PROMPT;
    }

    private function buildPreamble(): string
    {
        return <<<'PROMPT'
        You are an autonomous AI coding agent working on a Laravel project. You are one iteration
        in a loop managed by the Ralph system. Your job is to implement a single user story from
        the PRD, run quality checks, and commit your work.
        PROMPT;
    }

    private function buildPrdSection(Prd $prd): string
    {
        $json = json_encode($prd->toArray(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        return <<<PROMPT
        ## Current PRD State

        ```json
        {$json}
        ```
        PROMPT;
    }

    private function buildStorySection(UserStory $story, int $iteration): string
    {
        $criteria = implode("\n", array_map(fn ($c) => "- {$c}", $story->acceptanceCriteria));

        return <<<PROMPT
        ## Your Task (Iteration {$iteration})

        Implement story **{$story->id}: {$story->title}**

        {$story->description}

        ### Acceptance Criteria
        {$criteria}
        PROMPT;
    }

    private function buildGatesSection(): string
    {
        $enabledGates = array_filter($this->gates, fn ($g) => $g['enabled'] ?? true);

        if (empty($enabledGates)) {
            return '';
        }

        $lines = ["## Quality Gates\n\nRun these commands after implementation. ALL must pass:"];

        foreach ($enabledGates as $gate) {
            $command = $gate['command'];
            $label = $gate['label'] ?? $command;

            $lines[] = "- **{$label}**: `{$command}`";

            if (isset($gate['fallback'])) {
                $lines[] = "  - Fallback: `{$gate['fallback']}`";
            }
        }

        return implode("\n", $lines);
    }

    private function buildProgressSection(string $progressContent): string
    {
        if (empty(trim($progressContent))) {
            return '';
        }

        return <<<PROMPT
        ## Progress Log (learnings from previous iterations)

        ```
        {$progressContent}
        ```
        PROMPT;
    }

    private function buildInstructions(UserStory $story): string
    {
        return <<<PROMPT
        ## Instructions

        1. Read the codebase to understand the existing structure
        2. Implement story {$story->id} according to its acceptance criteria
        3. Run ALL quality gates listed above — they must pass
        4. If gates pass: commit your changes with a message like "[Ralph] {$story->id}: {$story->title}"
        5. Update prd.json to set this story's "passes" field to true
        6. Append a summary of what you did to progress.txt
        7. Check if ALL stories in prd.json now have "passes": true
        8. If all stories pass, output exactly: {$this->completionSignal}
        PROMPT;
    }

    private function getPrdSchema(): string
    {
        return <<<'SCHEMA'
        {
            "project": "string — project name",
            "branchName": "string — git branch, use ralph/ prefix (e.g. ralph/user-auth)",
            "description": "string — brief description of the feature set",
            "userStories": [
                {
                    "id": "string — e.g. US-001",
                    "title": "string — short title",
                    "description": "string — what this story delivers",
                    "acceptanceCriteria": ["string — verifiable criteria"],
                    "priority": "number — 1 = highest",
                    "passes": false,
                    "notes": "",
                    "issueNumber": "number|null — GitHub issue number if imported"
                }
            ]
        }
        SCHEMA;
    }
}
