# Vehicle Workshop Management System

A pure PHP modular monolith for small-to-medium vehicle workshops (max 200 staff, ~2,000 active customers). No framework. No ORM. Domain-driven design derived directly from ER, State Machine, Activity, and Use Case specifications.

## Architecture Overview

```text
vehicle-workshop/
├── migrations/                # Ordered SQL schema migrations
│   ├── 001.sql
│   ├── 002.sql
│   └── 0xx.sql
│
├── packages/                  # Pure PHP libraries (zero framework dependency)
│   ├── domain-core/           # Entities, Value Objects, Domain Events
│   ├── validator/             # Input validation rules (NIC, VIN, email, etc.)
│   ├── valkey-async/          # Valkey queue + pub/sub client
│   ├── module-contracts/      # Facade interfaces (cross-module API surface)
│   └── shared/                # Base classes, exceptions, UUID, Money wrapper
│
├── modules/                   # Business logic by bounded context
│   ├── Identity/              # Auth, sessions, stream tokens
│   ├── CustomerVehicle/       # Customers, vehicles, VIN parsing
│   ├── Appointment/           # Scheduling, slot management
│   ├── Job/                   # Repair workflow, state machine, QA
│   ├── Inventory/             # Parts, stock, compatibility
│   ├── Supplier/              # Suppliers, bills, authorization
│   ├── Billing/               # Quotations, invoices, payments
│   ├── Staff/                 # Profiles, availability, roles
│   ├── Notification/          # Event listener, message dispatch
│   └── SystemConfig/          # Settings, backups, templates
│
├── app/                       # Application layer
│   ├── http/
│   │   ├── controllers/       # Request handlers (delegate to facades)
│   │   └── middleware/        # Auth, RBCA, StreamToken
│   ├── resources/views/       # Server-rendered PHP templates
│   │   ├── customer/          # Customer Web App pages
│   │   ├── staff/             # Staff Web App pages
│   │   └── components/        # Reusable partials
│   ├── router.php             # Trie-based URL dispatcher
│   ├── container.php          # Lightweight DI container
│   ├── pipeline.php           # Middleware pipeline
│   └── bootstrap.php          # Wiring + autoloader
│
├── config/
│   ├── routes.php             # Route → Controller mapping
│   ├── services.php           # Interface → Implementation bindings
│   └── .env.example
│
├── bin/
│   ├── console                # CLI admin commands
│   └── queue-worker           # Valkey async worker (long-running)
│
├── public/
│   └── index.php              # Single HTTP entry point
│
├── tests/                     # Unit (per module) + Integration (cross-module)
│
├── tools/
│   ├── deptrac.yaml           # Architecture boundary enforcement
│   └── phpstan.neon           # Static analysis at max level
│
├── docker-compose.yml
└── composer.json
```

## Prerequisites

-   PHP 8.3+ (with `pdo_pgsql`, `redis` extensions)
-   PostgreSQL 15+
-   Valkey (Redis-compatible) 7+
-   Composer 2.x
-   Docker & Docker Compose

## Quick Start

### 1. Clone and Install

```bash
git clone <repo-url> vehicle-workshop
cd vehicle-workshop
composer install
cp config/.env.example config/.env
# Edit config/.env with your DB/Valkey credentials
```

### 2. Start Infrastructure

```bash
docker compose up -d
# Starts: PHP-FPM, Nginx, PostgreSQL, Valkey
```

### 3. Run Migrations

```bash
php bin/console migrate
```

### 4. Start Queue Worker

```bash
php bin/queue-worker
# Keep this running in a separate terminal or as a systemd service
# Processes: notifications, restock requests, backup jobs
```

### 5. Access the Application

| App              | URL                           | Default Login               |
| ---------------- | ----------------------------- | --------------------------- |
| Customer Web App | http://localhost/customer     | customer@test.com / password |
| Staff Web App    | http://localhost/staff        | technician@test.com / password |
| Admin Panel      | http://localhost/admin        | admin@test.com / password    |

## Module Dependency Rules

Cross-module communication follows strict rules enforced by Deptrac:

| Allowed                                         | Forbidden                                            |
| ----------------------------------------------- | ---------------------------------------------------- |
| Module → `packages/module-contracts/*` (interfaces only) | Module → Another module's `Internal/` directory      |
| Module → `packages/domain-core/*` (entities/events)     | Module → Another module's Repository/Mapper          |
| Module → `packages/shared-kernel/*`                    | Direct entity instantiation across modules           |
| Async events via Valkey Pub/Sub                        | Synchronous calls to NotificationModule              |
| Shared DB transactions (Job + Inventory)                 | Cross-module DB queries without facade               |

Run enforcement:

```bash
vendor/bin/deptrac analyse --config=tools/deptrac.yaml
vendor/bin/phpstan analyse --configuration=tools/phpstan.neon
```

## Key Architectural Decisions

### Why Modular Monolith Over Microservices?

At 200 staff / 2K customers, horizontal scaling is unnecessary. A single deployable unit provides ACID transactions across modules, eliminates network overhead, and reduces operational complexity. Module boundaries are enforced via code (Deptrac + facade interfaces), not network isolation.

### Why Pure PHP Over Laravel/Symfony?

The domain model (ER + State Machines + Activities) drives all abstractions. Framework ORMs and service containers add generic overhead that doesn't align with workshop-specific workflows. Our DI container is ~80 lines. Our router is ~120 lines. Zero transitive dependency bloat.

### Why Server-Rendered Templates Over SPA?

Garages have spotty internet and old devices. Server-rendered PHP templates work on any browser with zero JS bundle. HTMX can be added later for progressive enhancement without abandoning this approach.

### Async Strategy

-   **Synchronous** (same DB transaction): Job parts consumption, quotation generation, payment recording
-   **Async via Valkey Queue**: Notification dispatch, PDF generation, backup execution
-   **Async via Valkey Pub/Sub**: Domain event fan-out (JobCompleted → Notification, LowStock → SupplierOrder)

## Testing

```bash
# Unit tests (per module, no DB needed)
vendor/bin/phpunit --testsuite=unit

# Integration tests (requires running DB + Valkey)
vendor/bin/phpunit --testsuite=integration

# Architecture enforcement
vendor/bin/deptrac analyse --config=tools/deptrac.yaml

# Static analysis
vendor/bin/phpstan analyse --configuration=tools/phpstan.neon

# Mutation testing
vendor/bin/infection --min-msi=80
```

<!-- ## CLI Commands -->
<!---->
<!-- ```bash -->
<!-- php bin/console migrate              # Run pending migrations -->
<!-- php bin/console migrate:rollback     # Rollback last migration batch -->
<!-- php bin/console staff:create         # Interactive staff account creation -->
<!-- php bin/console staff:deactivate ID  # Deactivate staff member -->
<!-- php bin/console backup:create        # Create database backup -->
<!-- php bin/console backup:restore FILE  # Restore from backup file -->
<!-- php bin/console config:set KEY VALUE # Update system setting -->
<!-- ``` -->
<!---->
## Adding a New Endpoint

1.  Define the route in `config/routes.php`
2.  Create controller method in `app/Http/Controllers/`
3.  Add facade method to interface in `packages/module-contracts/`
4.  Implement in module's `Facade/` class
5.  Add business logic in module's `Internal/` services
6.  Write unit test for service + integration test for endpoint
7.  Run `deptrac` to verify no boundary violations

## Production Deployment

```bash
# Build optimized autoloader
composer dump-autoload --optimize --no-dev

# Set environment
APP_ENV=production
APP_DEBUG=false

# Queue worker as systemd service
# See docs/systemd/queue-worker.service

# Nginx serves static assets directly
# Only PHP requests hit index.php
```

## Reference Documents

All architectural decisions trace back to these specification artifacts:

-   `docs/ER.drawio` — Entity relationships and attributes
-   `docs/UseCases.drawio` — Actor interactions and module boundaries
-   `docs/Activities.drawio` — Workflow sequences and decision points
-   `docs/StateMachines.drawio` — Lifecycle states and guard conditions
