# Security Policy

This document describes how to report vulnerabilities for Vito Business OS and the security controls currently visible in this repository as of 2026-03-12.

## Supported Scope

We currently support security fixes for the latest code in the default branch of this repository.

If you are running an old fork, stale deployment, or heavily customized branch, upgrade to the latest maintained state first whenever possible.

## Reporting a Vulnerability

1. Do not open a public issue for suspected vulnerabilities.
2. Report the issue privately to the repository owner or designated security contact.
3. Include the affected surface, impact, reproduction steps, and any tenant or auth context needed to validate the issue.
4. If the report touches payments, auth, tenancy isolation, signed URLs, webhooks, or secrets, flag it as high priority.
5. Remove credentials, tokens, personal data, and production secrets from screenshots or attachments.

Target handling expectations:

- Acknowledge receipt as soon as practical, ideally within 48 hours.
- Triage severity and reproduction status before discussing disclosure.
- Coordinate fixes and release timing before any public write-up.

## Security Controls Visible in This Repo

The codebase already includes the following defensive layers:

- Authentication with Laravel Fortify for web flows and Sanctum for protected API flows.
- OTP and social login flows with rate limiting and idempotency protections.
- Multi-tenant isolation through policies, tenant-scoped queries, and the `tenant.ownership` middleware.
- Signed URLs for guest appointment management and secure report/export downloads.
- Pre-signed upload support for sensitive billing evidence and private storage workflows.
- Payment webhook verification backed by `PAYMENT_WEBHOOK_SECRET`, webhook logging, and idempotency guards.
- Contextual rate limiting for login, OTP, uploads, notifications, transactions, and tenant-aware public traffic.
- Security response headers via `App\Http\Middleware\SecurityHeaders`.
- RBAC via Spatie Permission plus admin and tenant panel segregation.
- Realtime channel authorization for tenant, tenant-admin, user, and order scoped broadcasts.
- Auditability through activity logs, health tooling, and operational observability.

## Contributor Expectations

When adding or changing functionality:

- Validate all input with Form Requests or command validators.
- Enforce authorization with policies, guards, and tenant ownership checks.
- Do not bypass signed URL, auth, CSRF, or webhook verification middleware without a documented reason.
- Avoid rendering unsanitized HTML or unsafe `dangerouslySetInnerHTML` paths.
- Add or update tests for auth, IDOR, tenancy, rate limiting, webhooks, or security headers when relevant.
- Never commit secrets, local dumps, debug artifacts, or ad-hoc evidence files.

## Local Security Notes

- Keep secrets only in `.env` or your secret manager; never commit them.
- Define `PAYMENT_WEBHOOK_SECRET` locally before exercising webhook flows or route-heavy Artisan commands.
- Use fake or sandbox credentials in local and CI environments.
- Review `SECURITY.md`, `README.md`, and `docs/ARCHITECTURE.md` together when changing security-sensitive behavior.