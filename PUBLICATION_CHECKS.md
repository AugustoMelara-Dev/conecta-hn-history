# Publication checks — 9 October 2026

## Recorded evidence

- Based on GitHub main `d2e10dbf1c9050a46dd48e01eeb757d20cb63537`, copied into an isolated archive.
- Private environments, storage, dependency/build artifacts, internal reports and agent state were filtered from history. The original working checkout was not reset, cleaned or overwritten.
- First retained commit remains dated 23 November 2025. Historical author/committer dates and author names were preserved. The founder's personal email metadata was changed to GitHub noreply for public privacy; no dates were fabricated.
- `git fsck --full --no-reflogs` and `git diff --check` passed.
- Gitleaks 8.30.1 executable matched its official release checksum.
- Retained history scan passed with **27 exact reviewed synthetic-fixture fingerprints**. Entire test directories were not excluded. Reasons are in PUBLICATION_FIXTURES.json.

## Limits and final gates

- Scanner results do not prove absence of all private content or vulnerabilities. Independent publication review remains required.
- Complete dependency installation, functional application tests, databases and provider integrations were not certified.
- Local Laravel WIP is held separately, not automatically included in the archive.
- No GitHub visibility change, public upload or force-push was performed.

## Recommended release

Publish a separately approved public archive with the sanitized history and preserve the original private working repository. Replacing the original history requires specific approval because it changes commit identities and references. Private pull-request/cached references require separate consideration before changing visibility.
