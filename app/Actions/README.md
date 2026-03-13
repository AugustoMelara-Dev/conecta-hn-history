# Actions Layer (Infrastructure Adapters)

This directory functions as the **Driving Adapters Layer** in our Hexagonal Architecture. Classes here translate external framework signals (HTTP requests, Fortify contracts) into internal Application Commands.

## Bounded Context Map

### Authentication (`app/Actions/Authentication`)

**Scope:** Access control, credential management, and session security.

- **Responsibility:** Verifying _who_ the user is.
- **Examples:** Password resets (`ResetPasswordAction`), password updates (`UpdateUserPassword`).

### Identity (`app/Actions/Identity`)

**Scope:** User lifecycle, registration, profile management, and tenancy context.

- **Responsibility:** Managing _what_ the user is, _where_ they belong, and _how_ they present themselves.
- **Examples:** User registration (`CreateNewUser`), profile updates (`UpdateUserProfileInformation`).

### Interactions (`app/Actions/Interactions`)

**Scope:** Analytics and behavioral tracking.

- **Responsibility:** High-throughput recording of user engagement.
- **Examples:** Recording page views, clicks, or business metrics (`RecordInteractionAction`).

## Engineering Standards

1. **No Business Logic:** Logic belongs in `App\Application\{Context}\Handlers`.
2. **Strict Typing:** All inputs must be validated and mapped to Command DTOs.
3. **Command Bus:** Delegate execution immediately to `CommandBusInterface`.

## Architectural Drivers

### 1. Anti-Corruption Layer (ACL) - Vendor Isolation

Fortify imposes weakly typed signatures (`mixed $user`, `array $input`). Without this layer, business logic would be tightly coupled to framework interfaces.

- **Solution:** Actions act as a contractual firewall, translating untyped framework signatures into strictly typed, immutable Commands (for example, `RegisterUserCommand`). This prevents framework contract evolution from rippling into the Domain.
- **Vendor Isolation:** Fortify, Sanctum, and other framework contracts are contained exclusively at this layer. Swapping the authentication vendor requires changes only here while the Domain and Application layers remain shielded.

### 2. Temporal Decoupling

Delegating orchestration to `CommandBusInterface` ensures Actions do not presume synchronous execution.

- **Impact:** Configuration changes in the bus allow scaling write-heavy operations without modifying Action code. This enables Eventual Consistency as a deployment-time decision, not a code-time decision.

### 3. Microservices Readiness - Surgical Seams for Service Extraction

The rigid Validate -> Map -> Dispatch pattern creates a precise cut-point for service extraction.

- **Microservices Readiness:** Each Action is a thin ACL adapter with a single dispatch call. Extracting a bounded context only requires replacing the local `CommandBusInterface` implementation with an HTTP or gRPC adapter.
- **Evolution:** The Actions remain completely untouched because the seam is clean and surgical.

### 4. Security Layer Separation

Input validation (for example, password rules, uniqueness rules, metadata size caps) occurs exclusively in this adapter layer.

- **Guarantee:** The Domain Layer (Handlers) never receives unsanitized or invalid data. Validation is an infrastructure concern and Handlers trust their Command inputs implicitly.
