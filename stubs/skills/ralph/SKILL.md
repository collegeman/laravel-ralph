---
name: ralph
description: "Convert a PRD markdown document into prd.json format for Ralph autonomous execution. Use when you have a PRD in tasks/ and need to create or update prd.json. Triggers on: convert prd to json, create prd.json, ralph json, prepare for ralph."
user-invocable: true
argument-hint: "[path to PRD markdown file]"
---

# Ralph PRD-to-JSON Converter

Convert a Product Requirements Document into `prd.json` format for autonomous execution by Ralph.

---

## The Job

1. Read the PRD file at `$ARGUMENTS` (or ask the user which PRD to convert from `tasks/`)
2. Extract user stories and convert to `prd.json` format
3. Validate the stories meet Ralph's requirements
4. Save as `prd.json` in the project root

---

## Story Requirements

### Size Constraint
Each story must be completable in **ONE Ralph iteration** (one context window). If a story is too large, break it into smaller stories. Signs a story is too large:
- More than 3-4 files need to change
- Multiple migrations required
- Both backend and frontend work in one story

### Dependency Ordering
Stories execute by priority (1 = first). Earlier stories cannot depend on later ones. Follow this sequence:
1. Database migrations and models
2. Backend logic (services, repositories, jobs)
3. Controllers and routes
4. Form Requests, Resources, Policies
5. Views/components (Blade, Livewire, Inertia)
6. Integration and feature tests

### Verifiable Acceptance Criteria
Each criterion must be something Ralph can CHECK, not something vague.

**Good:**
- "Migration creates `orders` table with `user_id`, `total`, `status` columns"
- "POST `/api/orders` returns 201 with order resource"
- "Unauthorized users receive 403 from OrderPolicy"
- "PHPStan passes"
- "Tests pass"

**Bad:**
- "Works correctly"
- "Good UX"
- "Handles edge cases"

### Required Criteria
Every story MUST include:
- "PHPStan passes"
- "Tests pass"

---

## prd.json Schema

```json
{
    "project": "string — project name (from composer.json or PRD title)",
    "branchName": "string — git branch name, e.g. feature/user-auth",
    "description": "string — one-line summary of the feature set",
    "userStories": [
        {
            "id": "US-001",
            "title": "Short descriptive title",
            "description": "Full description of what this story delivers",
            "acceptanceCriteria": [
                "Specific verifiable criterion",
                "PHPStan passes",
                "Tests pass"
            ],
            "priority": 1,
            "passes": false,
            "notes": ""
        }
    ]
}
```

If importing from GitHub Issues, include `"issueNumber"` on each story.

---

## Before Saving

1. If a `prd.json` already exists with a **different `branchName`**, warn the user — they may want to archive or reset it first (`php artisan ralph:reset --all`)
2. Validate that all stories have "PHPStan passes" and "Tests pass" in acceptance criteria
3. Confirm the branch name with the user

---

## Output

Save to `prd.json` in the project root. Use `JSON_PRETTY_PRINT` formatting.

After saving, suggest:
```
Your prd.json is ready with [N] stories. Next steps:
1. Review the stories: php artisan ralph:status
2. Start the loop:     php artisan ralph:run
3. Or dry-run first:   php artisan ralph:run --dry-run
```
