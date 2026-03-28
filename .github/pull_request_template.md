## PR Description
<!-- Describe your changes -->

---

## Startup Release Checklist
- [ ] No reopens legacy writes or new active flows under `/workspace`
- [ ] Commercial copy matches shipped product today; no phantom builder, no unsupported billing/fiscal claims, no invented metrics
- [ ] Critical gates pass locally when relevant: `composer test:release-critical`
- [ ] Tenant isolation remains explicit for every sensitive write/read/export flow touched
- [ ] Browser release gate was rerun if this PR changes booking, checkout, billing, CRM, superadmin, public microsite, or onboarding

---

## General Checklist
- [ ] Changes are backward compatible
- [ ] Code follows project conventions
- [ ] No hardcoded secrets
- [ ] Presentation only orchestrates; business rules stay out of controllers/pages/resources

---

## Domain Event Checklist (IF ADDING/MODIFYING EVENTS)

> **MANDATORY:** Every event PR must pass ALL checks below. Unchecked = Rejection.

### Architecture (ADR-002)
- [ ] Class is `final readonly`
- [ ] Implements `DomainEvent` or `AuditEvent`
- [ ] Uses `HasDomainMetadata` trait
- [ ] Has static `::create()` factory
- [ ] Has `toPrimitives(): array` method
- [ ] Has `fromPrimitives(array): self` method

### Purity
- [ ] NO Eloquent models in properties
- [ ] NO `SerializesModels` trait
- [ ] All properties are `private` or `readonly`

### Naming
- [ ] Event name in past tense (OrderPaid, not PayOrder)
- [ ] Single responsibility (one business fact)

---

## References
- [ADR-002 Standards](docs/architecture/decisions/ADR-002-domain-events-governance.md)
- [Event Creation Guide](docs/architecture/EVENTS_GUIDE.md)
- Template: `php artisan make:domain-event YourEvent --bounded=Order`
