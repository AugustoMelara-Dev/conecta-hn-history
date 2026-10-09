# Publication checks — 9 October 2026

## Recorded evidence

- Based on GitHub main `d2e10dbf1c9050a46dd48e01eeb757d20cb63537`, copied into an isolated archive.
- Private environments, storage, dependency/build artifacts, internal reports and agent state were filtered from history. The original working checkout was not reset, cleaned or overwritten.
- First retained commit remains dated 23 November 2025. Historical author/committer dates and author names were preserved. The founder's personal email metadata was changed to GitHub noreply for public privacy; no dates were fabricated.
- `git fsck --full --no-reflogs` and `git diff --check` passed.
- Gitleaks 8.30.1 executable matched its official release checksum.
- Retained history scan passed with **27 exact reviewed synthetic-fixture fingerprints**. Entire test directories were not excluded. Reasons are in PUBLICATION_FIXTURES.json.

## Limits and final gates

- Independent publication review completed on sanitized source commit `78e71a7bdc176a671c2ac27a4a53f2f4fbb0b703`: 60 historical artifact exclusions absent; personal author email metadata sanitized; five milestone mappings and 27 exact fixture fingerprints checked; Gitleaks, fsck and diff checks passed. The later setup documentation describes empty local runtime directories.
- Scanner/review results do not prove absence of every private datum or certify functional application security.
- Complete dependency installation, functional application tests, databases and provider integrations were not certified.
- Local Laravel WIP is held separately, not automatically included in the archive.
- The sanitized archive was uploaded to the new public `conecta-hn-history` repository on 9 October 2026. The original private repository was not made public or force-pushed.

## Repository separation

Publication target: `AugustoMelara-Dev/conecta-hn-history`, using only `refs/heads/main`. Local historical tags are excluded from upload. The original working repository remains private with its original branches and pull-request references; its history is not overwritten. The current Next.js implementation remains in the separate `dovra-next` repository.
