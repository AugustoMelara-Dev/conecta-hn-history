# Shared Kernel - Architectural Contract

**Location:** `app/Domain/Shared/`

## Purpose
Contains shared building blocks used across multiple Bounded Contexts.

## Allowed Artifacts
- Value Objects (`Money`, `EmailAddress`, `TenantId`)
- Interfaces/Contracts (`TenantAware`, `Auditable`)
- Traits (cross-cutting concerns)
- DTOs (Data Transfer Objects)
- Middleware (cross-domain)

## Strictly Prohibited
❌ Eloquent Models
❌ Controllers
❌ Business Logic
❌ Database Access
❌ Domain Events (use Domain/{Context}/Events/)

## Dependency Rule
```
Shared → NOBODY (zero outbound dependencies)
Everyone → Shared (any domain may depend on Shared)
```

## Enforcement
Run `php artisan test tests/Arch/DomainStructureTest.php` to validate.
