# Application Layer - Orchestration & Use Cases

**Namespace**: `App\Application`

---

## Responsabilidad

La **Application Layer** es la capa de orquestación que coordina casos de uso de negocio. Actúa como intermediario entre la infraestructura (HTTP, CLI, Queues) y el dominio puro. Esta capa es responsable de: iniciar transacciones, coordinar múltiples agregados, disparar eventos de dominio post-persistencia, y delegar tareas asíncronas a workers.

## Artefactos Principales

| Tipo         | Directorio  | Propósito                           |
| ------------ | ----------- | ----------------------------------- |
| **Jobs**     | `Jobs/`     | Comandos asíncronos (colas)         |
| **Actions**  | `Actions/`  | Casos de uso síncronos              |
| **DTOs**     | `DTOs/`     | Objetos de transferencia de datos   |
| **Services** | `Services/` | Application Services (orquestación) |

### Jobs por Contexto

```
Jobs/
├── AI/           → EnhanceTenantProfileJob
├── Analytics/    → CalculateTenantMetricsJob, etc.
├── Exports/      → GenerateSalesExportJob
├── Governance/   → ProcessSubscriptionJob
├── Marketing/    → SendCampaignJob
├── Media/        → GenerateProductBlurhash
└── Reporting/    → GeneratePdfReportJob
```

## Reglas de Interacción (Boundaries)

### ✅ PERMITIDO

- Application **PUEDE** depender de Domain Layer
- Application **PUEDE** usar interfaces de Infrastructure
- Application **PUEDE** emitir Domain Events
- Jobs **DEBEN** recibir solo primitivos o DTOs (no Eloquent)

### ❌ PROHIBIDO

- Application **NO debe** contener lógica de negocio (delegar a Domain)
- Application **NO debe** depender de Presentation (Filament, Blade)
- Application **NO debe** instanciar modelos Eloquent directamente en constructores

## Patrón de Job Correcto

```php
final class MyJob implements ShouldQueue
{
    public function __construct(
        public readonly int $tenantId,  // ✅ Primitivo
        public readonly array $payload  // ✅ Array
    ) {}

    public function handle(MyService $service): void
    {
        $tenant = Tenant::findOrFail($this->tenantId); // ✅ Hidratación en handle()
        $service->execute($tenant);
    }
}
```

## Dependencias

```
Application → Domain (Entities, ValueObjects, Events)
Application → Infrastructure Contracts (Repositories interfaces)
Application → Laravel Queue/Job Contracts
```

## Testing

```bash
php artisan test tests/Application/
```

---

## Namespace Conventions

### Contracts & Ports

All Application-layer ports reside in `App\Application\{Context}\Contracts`.

- **DO NOT** duplicate ports in `App\Domain\{Context}\Ports`.
- Domain-layer contracts (repository interfaces) stay in `App\Domain\{Context}\Repositories`.

### Commands

All Command DTOs reside in `App\Application\{Context}\Commands`.

- Commands must use **only primitives**, lightweight VOs, or PHP enums.
- Commands carrying passwords must implement `SensitiveDataInterface`.

### Domain Layer (`App\Domain`)

The Domain layer is **active** and contains core business artifacts:

- Entities, Value Objects, Enums, Domain Events
- Repository interfaces, Domain Exceptions
- **DO NOT** delete or merge into `App\Application`.
