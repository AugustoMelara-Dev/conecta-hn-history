# TenantGovernance Context - Bounded Context

**Namespace**: `App\Domain\TenantGovernance`

---

## Responsabilidad

El contexto de **TenantGovernance** constituye el **Core Domain** del sistema multi-tenant. Gestiona el ciclo de vida de los negocios (tenants), incluyendo su creación, configuración, suscripciones, moderación y estados de aprobación. Este módulo implementa las reglas de negocio que determinan qué puede hacer un negocio según su plan (Free vs Pro), estado de suscripción, y cumplimiento normativo.

## Artefactos Principales

| Tipo | Clase | Propósito |
|------|-------|-----------|
| **Aggregate Root** | `Tenant` | Entidad principal de multi-tenancy |
| **Factory** | `TenantFactory` | Generación de fixtures para testing |
| **Service Provider** | `TenantGovernanceServiceProvider` | Binding del contexto |

## Reglas de Interacción (Boundaries)

### ✅ PERMITIDO
- **Application Layer** puede orquestar operaciones sobre Tenant
- **Infrastructure Layer** puede implementar adaptadores de suscripción
- Eventos de dominio pueden ser emitidos desde este contexto

### ❌ PROHIBIDO
- TenantGovernance **NO debe** contener Application Services (mover a `App\Application\Services\`)
- TenantGovernance **NO debe** importar Filament ni Laravel HTTP
- TenantGovernance **NO debe** acceder a Session/Request

## Dependencias

```
TenantGovernance → Identity (User relationship)
TenantGovernance → Shared Kernel (TenantId, ValueObjects)
TenantGovernance → Spatie MediaLibrary (via trait)
```

## Domain Events Emitidos

- `TenantCreated`
- `TenantApproved`
- `TenantSuspended`
- `SubscriptionStatusChanged`

## Testing

```bash
php artisan test tests/Domain/TenantGovernance/
```
