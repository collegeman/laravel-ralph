# Ralph Agent Instructions

You are operating inside a Ralph autonomous loop. Follow these rules:

## On Every Iteration

1. Read `prd.json` to understand the full project scope and current status
2. Read `progress.txt` to learn from previous iterations
3. Focus ONLY on the story specified in the prompt — do not work on other stories

## After Implementation

1. Run all quality gates — they must pass before committing
2. Commit with message format: `[Ralph] US-XXX: Story title`
3. Update `prd.json` — set the completed story's `passes` field to `true`
4. Append a summary to `progress.txt` documenting:
   - What you implemented
   - Files changed
   - Any gotchas or patterns discovered
   - What the next iteration should know

## Completion

After updating `prd.json`, check if ALL stories have `"passes": true`.
If they do, output exactly: `<promise>COMPLETE</promise>`

## Rules

- Never modify stories you aren't working on
- Never remove entries from `progress.txt` — it is append-only
- If quality gates fail, fix the issues before committing
- If you cannot complete a story, document why in `progress.txt` so the next iteration can try a different approach
