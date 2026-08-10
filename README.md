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

## Module Dependency Rules
Cross-module communication follows strict rules enforced by Deptrac:

### Allowed
Module → packages/module-contracts/* (interfaces only)
Module → packages/domain-core/* (entities/events)
Direct entity instantiation across modules
Synchronous calls to NotificationModule
Cross-module DB queries without facade

### Forbidden
Module → Another module's Internal/ directory
Module → Another module's Repository/Mapper
Module → packages/shared-kernel/*
Async events via Valkey Pub/Sub
Shared DB transactions (Job + Inventory)
