# Identity Context - Bounded Context

**Namespace**: `App\Domain\Identity`

---

## Responsabilidad

El contexto de **Identity** es el núcleo de autenticación y autorización del sistema. Gestiona el ciclo de vida completo de usuarios, incluyendo registro, verificación de email, desactivación/reactivación de cuentas, y la resolución de roles (RBAC). Este módulo es la **Fuente Única de Verdad** para determinar quién puede acceder al sistema y qué acciones puede realizar.

## Artefactos Principales

| Tipo | Clase | Propósito |
|------|-------|-----------|
| **Aggregate Root** | `User` | Entidad principal de identidad |
| **Factory** | `UserFactory` | Generación de fixtures para testing |
| **Service Provider** | `IdentityServiceProvider` | Binding de servicios del contexto |

## Reglas de Interacción (Boundaries)

### ✅ PERMITIDO
- Otros contextos de **Domain** pueden depender de Identity
- **Application Layer** puede orquestar casos de uso de Identity
- **Infrastructure Layer** puede implementar adaptadores (OAuth, etc.)

### ❌ PROHIBIDO
- Identity **NO debe** importar clases de Filament
- Identity **NO debe** depender de TenantGovernance (evitar ciclo)
- Identity **NO debe** acceder directamente a HTTP Request

## Dependencias

```
Identity → Shared Kernel (ValueObjects)
Identity → Laravel Auth Contracts (Authenticatable)
```

## Testing

```bash
php artisan test tests/Domain/Identity/
```
