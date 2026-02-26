# Actions Layer (Infrastructure Adapters)

This directory functions as the **Driving Adapters Layer** in our Hexagonal Architecture. Classes here translate external framework signals (HTTP requests, Fortify contracts) into internal Application Commands.

## Bounded Context Map

### 🔐 Authentication (`app/Actions/Authentication`)

**Scope:** Access control, credential management, and session security.

- **Responsibility:** Verifying _who_ the user is.
- **Examples:** Password Resets (`ResetPasswordAction`), Password Updates (`UpdateUserPassword`).

### 🆔 Identity (`app/Actions/Identity`)

**Scope:** User lifecycle, registration, profile management, and tenancy context.

- **Responsibility:** Managing _what_ the user is (Entities), _where_ they belong (Tenants), and _how_ they present themselves (Profile).
- **Examples:** User Registration (`CreateNewUser`), Profile Updates (`UpdateUserProfileInformation`).

### 📊 Interactions (`app/Actions/Interactions`)

**Scope:** Analytics and behavioral tracking.

- **Responsibility:** High-throughput recording of user engagement.
- **Examples:** Recording page views, clicks, or business metrics (`RecordInteractionAction`).

## Engineering Standards

1. **No Business Logic:** Logic belongs in `App\Application\{Context}\Handlers`.
2. **Strict Typing:** All inputs must be validated and mapped to Command DTOs.
3. **Command Bus:** Delegate execution immediately to `CommandBusInterface`.

## Architectural Drivers (Systemic Complexity Resolution)

### 1. Anti-Corruption Layer (ACL)

Fortify imposes weakly typed signatures (`mixed $user`, `array $input`). Without this layer, business logic would be tightly coupled to framework interfaces.

- **Solution:** Actions act as a **contractual firewall**, translating untyped framework signatures into strictly typed, immutable **Commands** (e.g., `RegisterUserCommand`). This prevents framework contract evolution from rippling into the Domain.

### 2. Temporal Decoupling

Delegating orchestration to `CommandBusInterface` ensures Actions do not presume synchronous execution.

- **Impact:** Configuration changes in the Bus (Sync → Queue) allow scaling write-heavy operations (e.g., `RecordInteractionAction`) without modifying the Action code. This enables **Eventual Consistency** as a deployment-time decision, not a code-time decision.

### 3. Surgical Seams for Microservices

The rigid `Validate → Map → Dispatch` pattern creates a precise cut-point for service extraction.

- **Evolution:** To extract a Bounded Context (e.g., `Identity`) to a microservice, replace the local `CommandBusInterface` implementation with an HTTP/gRPC adapter. The Actions remain **completely untouched** — the seam is clean and surgical.

### 4. Security Layer Separation

Input validation (e.g., `Password::default()`, uniqueness rules, metadata size caps) occurs **exclusively** in this Infrastructure Layer.

- **Guarantee:** The Domain Layer (Handlers) **never** receives unsanitized or invalid data. Validation is an infrastructure concern — Handlers trust their Command inputs implicitly.
