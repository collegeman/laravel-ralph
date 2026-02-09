# Laravel Ralph

An autonomous AI agent loop for Laravel. Ralph orchestrates [Claude Code](https://docs.anthropic.com/en/docs/claude-code) to implement features from a PRD (Product Requirements Document), one user story at a time, in a repeating loop until everything passes.

Inspired by [snarktank/ralph](https://github.com/snarktank/ralph).

## How It Works

1. You define user stories in a `prd.json` file (or let Claude generate one)
2. Ralph spawns a fresh Claude Code instance for each iteration
3. Each iteration: pick the next story, implement it, run quality gates, commit
4. Memory persists across iterations via `progress.txt` and git history
5. Loop exits when all stories pass or max iterations reached

## Requirements

- PHP 8.2+
- Laravel 11 or 12
- [Claude Code](https://docs.anthropic.com/en/docs/claude-code) CLI installed and authenticated
- [GitHub CLI](https://cli.github.com) (`gh`) for GitHub sync features (optional)

## Installation

```bash
composer require collegeman/laravel-ralph
```

## Quick Start

```bash
# Scaffold project files
php artisan ralph:init

# Generate a PRD interactively
php artisan ralph:prd

# Run the loop
php artisan ralph:run
```

## Why Laravel Ralph?

The [original Ralph](https://github.com/snarktank/ralph) is elegant in its simplicity — 80 lines of bash, no dependencies, works everywhere. So why use this package instead?

### Smarter prompts, less wasted context

Bash Ralph pipes a static CLAUDE.md and trusts Claude to read `prd.json` and `progress.txt` from disk. That works, but Claude spends tokens and turns just reading files before doing any real work. Laravel Ralph's PromptBuilder injects the PRD state, the target story, gate commands, and progress log directly into the prompt. Claude starts implementing immediately. Every iteration is more focused and wastes less context window on file discovery.

### Laravel-aware defaults out of the box

Bash Ralph requires you to configure quality checks yourself — you have to know what commands to run and edit the prompt template. This package ships with PHPStan + Pest/PHPUnit pre-configured, and the skills teach Claude Laravel conventions (Form Requests, Policies, Resources, migration ordering). Run `ralph:init` and the quality gates, story ordering guidance, and acceptance criteria conventions are already correct for your stack.

### GitHub Issues as a first-class input

`ralph:prd --from-issues` pulls issues labeled `ralph`, has Claude convert them to properly-sized stories, and `ralph:sync` pushes status back — closing issues when stories pass, commenting progress during iterations. Bash Ralph has no awareness of issue trackers. If your team files work as GitHub Issues, this closes the loop between project management and autonomous execution.

### Dry runs save real money

Before spending tokens on a loop that might run 10 iterations, `ralph:run --dry-run` shows you exactly what prompt Claude will receive. You can verify the story targeting, gate commands, and progress context look right. Bash Ralph has no equivalent — you run it and hope.

### Status visibility without jq

Bash Ralph's debugging is `cat prd.json | jq '.userStories[] | {id, title, passes}'`. `ralph:status` gives you a formatted table instantly. When you're monitoring a loop across multiple features, it matters.

### Config over code editing

Bash Ralph customization means editing shell scripts and prompt files. Laravel Ralph uses `config/ralph.php` — add a gate, change the model, adjust iteration limits, toggle GitHub sync. Standard Laravel config with environment overrides, publishable and version-controllable.

### When to use bash Ralph instead

If you're not using Laravel, if you want Amp CLI support, or if you want zero dependencies — use the [original](https://github.com/snarktank/ralph). It's simpler and works with any tech stack.

## Commands

### `ralph:init`

Scaffolds Ralph files for your project:

- `prd.json` — story definitions and status (with example stories)
- `CLAUDE.md` — agent instructions for Claude during loop iterations
- `.claude/skills/prd/SKILL.md` — `/prd` slash command for generating PRDs
- `.claude/skills/ralph/SKILL.md` — `/ralph` slash command for converting PRDs to prd.json
- `tasks/` — directory for PRD markdown files (used by the `/prd` skill)
- `progress.txt` — append-only log of learnings across iterations

```bash
php artisan ralph:init
php artisan ralph:init --force    # Overwrite existing files
```

### `ralph:prd`

Create or update `prd.json` from various sources:

```bash
# Interactive — describe what you want, Claude generates the PRD
php artisan ralph:prd

# From a markdown requirements doc
php artisan ralph:prd --from=requirements.md

# From GitHub Issues (filtered by label)
php artisan ralph:prd --from-issues
php artisan ralph:prd --from-issues --label=v2
```

### `ralph:run`

The main loop. Iterates until all stories pass or the limit is hit.

```bash
php artisan ralph:run
php artisan ralph:run --max-iterations=20
php artisan ralph:run --story=US-003        # Target a specific story
php artisan ralph:run --dry-run             # Preview the prompt without invoking Claude
```

When the `branchName` in `prd.json` changes between runs, Ralph automatically archives the previous `prd.json` and `progress.txt` to `archive/` and resets progress for the new feature.

### `ralph:status`

Display current progress:

```
┌───────┬─────────────────────────────┬──────────┬────────┬─────────┐
│ ID    │ Title                       │ Priority │ Status │ Issue   │
├───────┼─────────────────────────────┼──────────┼────────┼─────────┤
│ US-001│ User login                  │ 1        │ Pass   │ #12     │
│ US-002│ Dashboard layout            │ 2        │ Todo   │ #15     │
│ US-003│ API endpoints               │ 3        │ Todo   │ #18     │
└───────┴─────────────────────────────┴──────────┴────────┴─────────┘
Progress ······························ 1/3 stories complete (33%)
```

### `ralph:sync`

Bidirectional sync with GitHub Issues:

```bash
php artisan ralph:sync          # Push story status to linked issues
php artisan ralph:sync --pull   # Pull new issues into prd.json
php artisan ralph:sync --both   # Full bidirectional sync
```

When a story passes and `close_on_pass` is enabled, Ralph closes the linked issue automatically.

### `ralph:reset`

Reset loop state for a fresh run:

```bash
php artisan ralph:reset --progress   # Clear progress.txt
php artisan ralph:reset --stories    # Reset all passes to false
php artisan ralph:reset --all        # Both
```

## Configuration

Publish the config file:

```bash
php artisan vendor:publish --tag=ralph-config
```

Key options in `config/ralph.php`:

```php
return [
    'max_iterations' => 10,
    'sleep_between_iterations' => 2,

    'claude' => [
        'binary' => 'claude',
        'model' => null,                    // Use Claude's default
        'max_turns' => null,
        'permission_mode' => 'bypassPermissions',
    ],

    // Quality gates — all must exit 0 for a story to pass
    'gates' => [
        'phpstan' => [
            'command' => './vendor/bin/phpstan analyse --no-progress --error-format=raw',
            'label' => 'PHPStan',
            'enabled' => true,
        ],
        'tests' => [
            'command' => './vendor/bin/pest --no-interaction',
            'fallback' => './vendor/bin/phpunit --no-interaction',
            'label' => 'Tests',
            'enabled' => true,
        ],
    ],

    'github' => [
        'enabled' => false,
        'label' => 'ralph',
        'close_on_pass' => true,
        'comment_on_progress' => true,
    ],
];
```

### Adding Custom Gates

Add any command as a quality gate. It just needs to exit 0 on success:

```php
'gates' => [
    'lint' => [
        'command' => './vendor/bin/pint --test',
        'label' => 'Pint',
        'enabled' => true,
    ],
    // ...
],
```

## PRD Format

```json
{
    "project": "my-app",
    "branchName": "ralph/user-auth",
    "description": "User authentication system",
    "userStories": [
        {
            "id": "US-001",
            "title": "User login",
            "description": "Users can log in with email and password",
            "acceptanceCriteria": [
                "Login form exists at /login",
                "Invalid credentials show error message",
                "Successful login redirects to /dashboard",
                "PHPStan passes",
                "Tests pass"
            ],
            "priority": 1,
            "passes": false,
            "notes": "",
            "issueNumber": 12
        }
    ]
}
```

Branch names use the `ralph/` prefix by convention (e.g., `ralph/contact-form`, `ralph/user-auth`). Each story should be small enough to complete in a single Claude session. Order by dependency — earlier stories should not depend on later ones.

## Claude Code Skills

`ralph:init` installs two [Claude Code skills](https://docs.anthropic.com/en/docs/claude-code/skills) as slash commands in your project:

### `/prd` — Generate a PRD

Use this inside Claude Code to interactively create a PRD markdown document. Claude asks clarifying questions, then generates a structured PRD saved to `tasks/prd-[feature-name].md`.

```
> /prd user authentication with OAuth
```

### `/ralph` — Convert PRD to prd.json

Takes a PRD markdown document and converts it into `prd.json` format, ready for the Ralph loop. Validates that stories are properly sized, ordered by dependency, and have verifiable acceptance criteria.

```
> /ralph tasks/prd-user-auth.md
```

### Workflow

The typical workflow is:

1. `/prd` — describe your feature, get a structured PRD
2. Review and edit the PRD in `tasks/`
3. `/ralph` — convert to `prd.json`
4. `php artisan ralph:run` — start the autonomous loop

Skills live in `.claude/skills/` and can be customized per-project. See the [Claude Code skills docs](https://docs.anthropic.com/en/docs/claude-code/skills) for details on the SKILL.md format.

## Archiving

Ralph automatically archives previous runs when the `branchName` in `prd.json` changes between `ralph:run` invocations. Archives are saved to `archive/YYYY-MM-DD-feature-name/` and include the previous `prd.json` and `progress.txt`. The progress log is then reset for the new feature.

To manually start fresh without archiving:

```bash
php artisan ralph:reset --all
```

## Key Files

| File | Purpose |
|------|---------|
| `prd.json` | User stories with `passes` status |
| `progress.txt` | Append-only learnings across iterations |
| `CLAUDE.md` | Agent instructions for each iteration |
| `.claude/skills/prd/SKILL.md` | `/prd` slash command |
| `.claude/skills/ralph/SKILL.md` | `/ralph` slash command |
| `tasks/` | PRD markdown files (intermediate step before prd.json) |
| `archive/` | Archived runs from previous features |
| `.ralph-branch` | Tracks current branch for archive detection |

## Tips

- **Always include quality gates in acceptance criteria.** Stories without "PHPStan passes" and "Tests pass" won't get verified.
- **Keep stories small.** One feature per story. If it feels too big, split it.
- **Review between runs.** Check git history after each `ralph:run` to validate the work before continuing.
- **Use `--dry-run` first.** Preview what Ralph will send to Claude before spending tokens.
- **Let progress.txt accumulate.** It's the memory that helps later iterations avoid repeating mistakes.
- **Use the `/prd` → `/ralph` workflow.** Generating a PRD first and reviewing it before conversion catches scope issues early.

## License

MIT
