# Program 12 runbook: observability, resilience, and operations

This repository-controlled slice defines the operational contract for Dovra Program 12 without modifying shared `app/Console`, `config/logging.php`, `config/queue.php`, `config/horizon.php`, or CI/container surfaces. Those remain a shared config seam and are reported honestly as `incomplete` when the evidence lives outside this write set.

## Trace and redaction contract

- `TraceContextInterface` is the contract name used by this slice for correlation requirements.
- Correlation must retain request/conversation, correlation ID, tenant/actor non-sensitive tenant/actor identifiers only, job/event, database/cache, provider, tool, integration, commercial outcome, idempotency key, and version.
- Correlation never includes credentials, secrets, raw prompts, message bodies, cookie values, bearer tokens, card data, or provider payload fragments.
- The current repository anchor is the existing Sentry scrubber plus fail-closed defaults:
  - `config/sentry.php`
  - `config/observability.php`
  - `config/logging.php`
  - `app/Infrastructure/Observability/SentryEventScrubber.php`

## Queue isolation and compensation contract

Required queue classes:

- `critical`
- `conversations`
- `channels`
- `workflows`
- `indexing`
- `imports`
- `analytics`
- `notifications`
- `maintenance`

Each queue contract requires tenant-safe and idempotent cleanup, correlation/version/idempotency on every job, bounded retry limits, and an explicit compensation contract. Shared runtime wiring of these classes is a shared config seam until the owning slice updates `config/queue.php` and `config/horizon.php`.

Current shared baseline observed from the tree:

- `config/queue.php` still defaults to the database queue connection.
- `config/horizon.php` still runs `['default']` with one shared supervisor definition.

That baseline is not upgraded here; instead this slice records the target topology, retry limits, and compensation contract so later shared-config work cannot silently drift.

## Initial objectives

- Monthly availability: 99.9%
- Non-AI request p95: <500 ms
- Healthy-provider first streamed chat p95: <2.5 s
- Critical jobs p95: <10 s
- RPO: 15 minutes
- RTO: 4 hours

## Incident categories covered by the contract

- provider outage
- webhook replay
- abnormal AI cost
- queue backlog or poison-message growth
- restore boundary confusion
- production config fail-closed review

## Fail-closed interpretation

- Missing shared observability inputs are `failed`.
- Missing queue topology upgrades outside this slice are `incomplete`, never `proved`.
- Managed AWS, DNS, secrets, and deployment smoke evidence remain `external` until executed against their real environments.
- Local scripts must never convert repository proof into managed AWS proof.
