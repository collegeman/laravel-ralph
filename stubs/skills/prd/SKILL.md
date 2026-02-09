---
name: prd
description: "Generate a Product Requirements Document (PRD) for a Laravel feature. Use when planning a feature, starting a new project, or when asked to create a PRD. Triggers on: create a prd, write prd for, plan this feature, requirements for, spec out."
user-invocable: true
argument-hint: "[feature description]"
---

# PRD Generator

Create detailed Product Requirements Documents that are clear, actionable, and suitable for autonomous implementation by Ralph.

---

## The Job

1. Receive a feature description from the user (via `$ARGUMENTS` or conversation)
2. Ask 3-5 essential clarifying questions (with lettered options)
3. Generate a structured PRD based on answers
4. Save to `tasks/prd-[feature-name].md`

**Important:** Do NOT start implementing. Just create the PRD.

---

## Step 1: Clarifying Questions

Ask only critical questions where the initial prompt is ambiguous. Focus on:

- **Problem/Goal:** What problem does this solve?
- **Core Functionality:** What are the key actions?
- **Scope/Boundaries:** What should it NOT do?
- **Success Criteria:** How do we know it's done?

### Format Questions Like This:

```
1. What is the primary goal of this feature?
   A. Improve user onboarding experience
   B. Increase user retention
   C. Reduce support burden
   D. Other: [please specify]

2. Who is the target user?
   A. New users only
   B. Existing users only
   C. All users
   D. Admin users only

3. What is the scope?
   A. Minimal viable version
   B. Full-featured implementation
   C. Just the backend/API
   D. Just the UI
```

This lets users respond with "1A, 2C, 3B" for quick iteration. Remember to indent the options.

---

## Step 2: PRD Structure

Generate the PRD with these sections:

### 1. Introduction/Overview
Brief description of the feature and the problem it solves.

### 2. Goals
Specific, measurable objectives (bullet list).

### 3. User Stories

Each story needs:
- **Title:** Short descriptive name
- **Description:** "As a [user], I want [feature] so that [benefit]"
- **Acceptance Criteria:** Verifiable checklist of what "done" means

Each story must be small enough to implement in **one Ralph iteration** (one context window).

**Format:**
```markdown
### US-001: [Title]
**Description:** As a [user], I want [feature] so that [benefit].

**Acceptance Criteria:**
- [ ] Specific verifiable criterion
- [ ] Another criterion
- [ ] PHPStan passes
- [ ] Tests pass
```

**Important:**
- Acceptance criteria must be verifiable, not vague. "Works correctly" is bad. "Returns 422 with validation errors when email is missing" is good.
- Always include "PHPStan passes" and "Tests pass" as acceptance criteria.
- For UI stories, include "Verify in browser" if browser testing is available.

### 4. Functional Requirements
Numbered list of specific functionalities:
- "FR-1: The system must allow users to..."
- "FR-2: When a user submits the form, the system must..."

Be explicit and unambiguous.

### 5. Non-Goals (Out of Scope)
What this feature will NOT include. Critical for managing scope.

### 6. Design Considerations (Optional)
- UI/UX requirements
- Existing Blade/Livewire/Inertia components to reuse
- Relevant Laravel packages already in the project

### 7. Technical Considerations (Optional)
- Known constraints or dependencies
- Migration requirements
- Queue/job processing needs
- Cache strategy
- API versioning

### 8. Success Metrics
How will success be measured?

### 9. Open Questions
Remaining questions or areas needing clarification.

---

## Laravel-Specific Guidance

When writing stories for Laravel projects:

- **Order by dependency:** Migrations and models first, then controllers/routes, then views/components
- **One migration per story** where possible
- **Form Requests** for validation, not inline rules
- **API Resources** for response transformation
- **Policies** for authorization
- **Events/Listeners** for side effects, not inline in controllers
- **Feature tests** for every story, not just unit tests

---

## Writing for AI Agents

The PRD reader will be an AI agent (Ralph). Therefore:

- Be explicit and unambiguous
- Avoid jargon or explain it
- Provide enough detail to understand purpose and core logic
- Number requirements for easy reference
- Use concrete examples where helpful
- Specify exact route paths, table names, and field names when known

---

## Output

- **Format:** Markdown (`.md`)
- **Location:** `tasks/`
- **Filename:** `prd-[feature-name].md` (kebab-case)

---

## Example PRD

```markdown
# PRD: Contact Form

## Introduction

Add a contact form that lets visitors send messages to the site admin. Messages are stored in the database and trigger an email notification.

## Goals

- Allow visitors to send messages without creating an account
- Validate all input server-side
- Persist messages for admin review
- Notify admin via email on new submissions

## User Stories

### US-001: Create contact_messages migration and model
**Description:** As a developer, I need to store contact form submissions in the database.

**Acceptance Criteria:**
- [ ] Migration creates contact_messages table with name, email, subject, message, and timestamps
- [ ] ContactMessage model has fillable fields
- [ ] ContactMessageFactory generates valid test data
- [ ] PHPStan passes
- [ ] Tests pass

### US-002: Create ContactRequest form validation
**Description:** As a developer, I need server-side validation for contact form submissions.

**Acceptance Criteria:**
- [ ] ContactRequest validates name (required, max:255), email (required, email), subject (required, max:255), message (required)
- [ ] Invalid submissions return 422 with field-specific errors
- [ ] PHPStan passes
- [ ] Tests pass

### US-003: Create ContactController and routes
**Description:** As a visitor, I want to submit the contact form and see confirmation.

**Acceptance Criteria:**
- [ ] GET /contact renders contact form view
- [ ] POST /contact stores message and redirects with success flash
- [ ] Routes registered in web.php
- [ ] PHPStan passes
- [ ] Tests pass

### US-004: Send admin notification on contact submission
**Description:** As an admin, I want to receive an email when someone submits the contact form.

**Acceptance Criteria:**
- [ ] ContactSubmitted notification created with toMail method
- [ ] Notification dispatched after successful form submission
- [ ] Email contains sender name, email, subject, and message
- [ ] PHPStan passes
- [ ] Tests pass

### US-005: Create contact form Blade view
**Description:** As a visitor, I want a clean contact form with validation feedback.

**Acceptance Criteria:**
- [ ] Contact form at /contact with name, email, subject, message fields
- [ ] Displays validation errors per field
- [ ] Shows success message after submission
- [ ] PHPStan passes
- [ ] Tests pass
- [ ] Verify in browser using dev-browser skill

## Functional Requirements

- FR-1: Store contact submissions in `contact_messages` table
- FR-2: Validate name, email, subject (required, max 255), message (required)
- FR-3: Send email notification to admin (configured via `MAIL_ADMIN_ADDRESS` env)
- FR-4: Show inline validation errors on form fields
- FR-5: Display success flash message after submission

## Non-Goals

- No spam protection (CAPTCHA) in this iteration
- No admin panel for viewing messages (use database directly)
- No reply-to-visitor functionality
- No file attachments

## Technical Considerations

- Use Laravel Notifications (not raw Mail) for admin email
- Store admin email in config, not hardcoded
- Use CSRF protection on the form
- Feature tests should use `Notification::fake()`

## Success Metrics

- Visitors can submit the form and see confirmation in under 3 seconds
- Admin receives email within 1 minute of submission
- Zero unvalidated submissions reach the database
```

---

## Checklist

Before saving the PRD:

- [ ] Asked clarifying questions with lettered options
- [ ] Incorporated user's answers
- [ ] User stories are small enough for one Ralph iteration
- [ ] Stories are ordered by dependency
- [ ] Every story includes "PHPStan passes" and "Tests pass" criteria
- [ ] Functional requirements are numbered and unambiguous
- [ ] Non-goals section defines clear boundaries
- [ ] Saved to `tasks/prd-[feature-name].md`
