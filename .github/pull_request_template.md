## 📋 PR Description
<!-- Describe your changes -->

---

## ✅ General Checklist
- [ ] Changes are backward compatible
- [ ] Code follows project conventions
- [ ] No hardcoded secrets

---

## 🚀 Domain Event Checklist (IF ADDING/MODIFYING EVENTS)

> **⚠️ MANDATORY:** Every event PR must pass ALL checks below. Unchecked = Rejection.

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

## 📚 References
- [ADR-002 Standards](docs/architecture/decisions/ADR-002-domain-events-governance.md)
- [Event Creation Guide](docs/architecture/EVENTS_GUIDE.md)
- Template: `php artisan make:domain-event YourEvent --bounded=Order`
