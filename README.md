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
composer require snarktank/laravel-ralph
```

## Quick Start

```bash
# Scaffold project files (prd.json, CLAUDE.md, progress.txt)
php artisan ralph:init

# Generate a PRD interactively
php artisan ralph:prd

# Run the loop
php artisan ralph:run
```

## Commands

### `ralph:init`

Scaffolds Ralph files for your project:

- `prd.json` — story definitions and status
- `CLAUDE.md` — agent instructions for Claude during loop iterations
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
    "branchName": "feature/user-auth",
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

Each story should be small enough to complete in a single Claude session. Order by dependency — earlier stories should not depend on later ones.

## Tips

- **Always include quality gates in acceptance criteria.** Stories without "PHPStan passes" and "Tests pass" won't get verified.
- **Keep stories small.** One feature per story. If it feels too big, split it.
- **Review between runs.** Check git history after each `ralph:run` to validate the work before continuing.
- **Use `--dry-run` first.** Preview what Ralph will send to Claude before spending tokens.
- **Let progress.txt accumulate.** It's the memory that helps later iterations avoid repeating mistakes.

## License

MIT
