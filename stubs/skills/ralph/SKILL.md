---
name: ralph
description: "Convert PRDs to prd.json format for the Ralph autonomous agent system. Use when you have an existing PRD and need to convert it to Ralph's JSON format. Triggers on: convert this prd, turn this into ralph format, create prd.json from this, ralph json."
user-invocable: true
argument-hint: "[path to PRD markdown file]"
---

# Ralph PRD Converter

Converts existing PRDs to the `prd.json` format that Ralph uses for autonomous execution in Laravel projects.

---

## The Job

Take a PRD (markdown file or text at `$ARGUMENTS`) and convert it to `prd.json` in the project root.

---

## Output Format

```json
{
    "project": "[Project Name]",
    "branchName": "ralph/[feature-name-kebab-case]",
    "description": "[Feature description from PRD title/intro]",
    "userStories": [
        {
            "id": "US-001",
            "title": "[Story title]",
            "description": "As a [user], I want [feature] so that [benefit]",
            "acceptanceCriteria": [
                "Criterion 1",
                "Criterion 2",
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

---

## Story Size: The Number One Rule

**Each story must be completable in ONE Ralph iteration (one context window).**

Ralph spawns a fresh Claude Code instance per iteration with no memory of previous work. If a story is too big, Claude runs out of context before finishing and produces broken code.

### Right-sized stories:
- Add a migration and Eloquent model
- Add a controller with one or two routes
- Create a Form Request with validation rules
- Add a Blade/Livewire component to an existing page
- Write feature tests for an endpoint

### Too big (split these):
- "Build the entire admin panel" — split into: schema, models, controllers, views, tests
- "Add user authentication" — split into: migration, model, login controller, registration controller, middleware, tests
- "Refactor the API" — split into one story per endpoint group
- "Add a complete CRUD" — split into: model/migration, create, read/list, update, delete

**Rule of thumb:** If you cannot describe the change in 2-3 sentences, it is too big.

---

## Story Ordering: Dependencies First

Stories execute by priority (1 = first). Earlier stories must not depend on later ones.

**Correct order for Laravel:**
1. Database migrations and Eloquent models
2. Service classes, repositories, or domain logic
3. Form Requests, Policies, API Resources
4. Controllers and routes
5. Blade views, Livewire/Inertia components
6. Feature tests and integration tests

**Wrong order:**
1. Controller (depends on model that doesn't exist yet)
2. Migration and model

---

## Acceptance Criteria: Must Be Verifiable

Each criterion must be something Ralph can CHECK, not something vague.

### Good criteria (verifiable):
- "Migration creates `orders` table with `user_id`, `total`, `status` columns"
- "POST `/api/orders` returns 201 with order resource JSON"
- "OrderRequest validates `total` as required numeric"
- "Unauthorized users receive 403 from OrderPolicy"
- "OrderFactory generates valid test data"
- "PHPStan passes"
- "Tests pass"

### Bad criteria (vague):
- "Works correctly"
- "User can manage orders easily"
- "Good error handling"
- "Handles edge cases"

### Always include as final criteria:
```
"PHPStan passes"
"Tests pass"
```

### For stories that change UI, also include:
```
"Verify in browser using dev-browser skill"
```

---

## Conversion Rules

1. **Each user story becomes one JSON entry**
2. **IDs**: Sequential (US-001, US-002, etc.)
3. **Priority**: Based on dependency order, then document order
4. **All stories**: `passes: false` and empty `notes`
5. **branchName**: Derive from feature name, kebab-case, prefixed with `ralph/`
6. **Always add**: "PHPStan passes" and "Tests pass" to every story's acceptance criteria

---

## Splitting Large PRDs

If a PRD has big features, split them:

**Original:**
> "Add a blog system"

**Split into:**
1. US-001: Create posts migration and Post model
2. US-002: Add PostFactory and PostSeeder
3. US-003: Create PostController with index and show routes
4. US-004: Create StorePostRequest and store action
5. US-005: Add PostPolicy for authorization
6. US-006: Create Blade views for post listing and detail
7. US-007: Add PostResource for API responses
8. US-008: Write feature tests for post CRUD

Each is one focused change that can be completed and verified independently.

---

## Example

**Input PRD:**
```markdown
# Contact Form Feature

Add a contact form that lets visitors send messages to the site admin.

## Requirements
- Contact form with name, email, subject, and message fields
- Validate all fields server-side
- Store messages in the database
- Send notification email to admin
- Show success/error feedback
```

**Output prd.json:**
```json
{
    "project": "MyApp",
    "branchName": "ralph/contact-form",
    "description": "Contact Form - Let visitors send messages to the site admin",
    "userStories": [
        {
            "id": "US-001",
            "title": "Create contact_messages migration and model",
            "description": "As a developer, I need to store contact form submissions in the database.",
            "acceptanceCriteria": [
                "Migration creates contact_messages table with name, email, subject, message, and timestamps",
                "ContactMessage model has fillable fields and factory",
                "PHPStan passes",
                "Tests pass"
            ],
            "priority": 1,
            "passes": false,
            "notes": ""
        },
        {
            "id": "US-002",
            "title": "Create ContactRequest form validation",
            "description": "As a developer, I need server-side validation for contact form submissions.",
            "acceptanceCriteria": [
                "ContactRequest validates name (required, max:255), email (required, email), subject (required, max:255), message (required)",
                "Invalid submissions return 422 with field-specific errors",
                "PHPStan passes",
                "Tests pass"
            ],
            "priority": 2,
            "passes": false,
            "notes": ""
        },
        {
            "id": "US-003",
            "title": "Create ContactController and routes",
            "description": "As a visitor, I want to submit the contact form and see confirmation.",
            "acceptanceCriteria": [
                "GET /contact renders contact form view",
                "POST /contact stores message and redirects with success flash",
                "Routes registered in web.php",
                "PHPStan passes",
                "Tests pass"
            ],
            "priority": 3,
            "passes": false,
            "notes": ""
        },
        {
            "id": "US-004",
            "title": "Send admin notification on contact submission",
            "description": "As an admin, I want to receive an email when someone submits the contact form.",
            "acceptanceCriteria": [
                "ContactSubmitted notification created with toMail method",
                "Notification dispatched after successful form submission",
                "Email contains sender name, email, subject, and message",
                "PHPStan passes",
                "Tests pass"
            ],
            "priority": 4,
            "passes": false,
            "notes": ""
        },
        {
            "id": "US-005",
            "title": "Create contact form Blade view",
            "description": "As a visitor, I want a clean contact form with validation feedback.",
            "acceptanceCriteria": [
                "Contact form at /contact with name, email, subject, message fields",
                "Displays validation errors per field",
                "Shows success message after submission",
                "PHPStan passes",
                "Tests pass",
                "Verify in browser using dev-browser skill"
            ],
            "priority": 5,
            "passes": false,
            "notes": ""
        }
    ]
}
```

---

## Archiving Previous Runs

**Before writing a new prd.json, check if there is an existing one from a different feature:**

1. Read the current `prd.json` if it exists
2. Check if `branchName` differs from the new feature's branch name
3. If different AND `progress.txt` has content beyond the header:
   - Create archive folder: `archive/YYYY-MM-DD-feature-name/`
   - Copy current `prd.json` and `progress.txt` to archive
   - Reset `progress.txt` with fresh header

Alternatively, tell the user to run `php artisan ralph:reset --all` before starting a new feature.

---

## Checklist Before Saving

Before writing prd.json, verify:

- [ ] **Previous run handled** (archived or reset if prd.json exists with different branchName)
- [ ] Each story is completable in one iteration (small enough)
- [ ] Stories are ordered by dependency (migrations → models → logic → controllers → views → tests)
- [ ] Every story has "PHPStan passes" and "Tests pass" as criteria
- [ ] UI stories have "Verify in browser using dev-browser skill" as criteria
- [ ] Acceptance criteria are verifiable (not vague)
- [ ] No story depends on a later story
- [ ] branchName uses `ralph/` prefix

After saving, suggest:
```
Your prd.json is ready with [N] stories. Next steps:
1. Review the stories: php artisan ralph:status
2. Start the loop:     php artisan ralph:run
3. Or dry-run first:   php artisan ralph:run --dry-run
```
