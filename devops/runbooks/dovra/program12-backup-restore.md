# Program 12 runbook: backup and restore boundaries

This runbook exists to keep repository proof and managed infrastructure proof separate.

## Repository-controlled local rehearsal

- The local rehearsal uses a synthetic fixture stored in `deployment/dovra/program12-local-restore-fixture.json`.
- The script writes a source snapshot, computes a SHA-256 hash, copies it into an isolated restore directory, restores it, and revalidates tenant/object counts.
- This local rehearsal does not prove managed PITR.
- The isolated restore directory is disposable local evidence only.

## Managed/cloud evidence that remains external

The following must come from the real provider and therefore stay external AWS proof until executed in that environment:

- `LatestRestorableTime`
- requested restore point
- observed RPO/RTO
- source and target ARN/IDs
- destruction approval for the isolated cloud restore

## Required operator language

- Say “local rehearsal” for repository evidence.
- Say “managed restore proof” only when the provider produced the evidence.
- Never infer PITR, RPO, or RTO solely from configuration files.

## Synthetic fixture checks

- tenant counts
- order/message/object counts
- artifact inventory
- source and restore hash equality

The synthetic fixture is intentionally narrow: it demonstrates repeatable local handling without overstating managed AWS proof.
