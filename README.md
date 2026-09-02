# Vwork — Vehicle Workshop Management System

A pure PHP modular monolith MVC for small-to-medium vehicle workshops (≤200 staff, ~2,000 active customers). No framework, no ORM, no HTMX

Routing, DI, and the request pipeline are all hand-rolled, on purpose, so the team learns PHP from first principles instead of a framework's opinions.

## The shape of the system

`vwork` consists of **five Composer packages**, each with its own `composer.json`, `src/`, and `tests/`.

| Folder | What it is | Depends on |
| --- | --- | --- |
| **`shared/`** | Small, dependency-free utilities — a generic trie, the exception hierarchy, validators. Zero domain knowledge, zero HTTP knowledge. | nothing |
| **`domain/`** | All business logic. Every module (Job, Billing, Staff, ...) and the infrastructure they run on (Postgres, Valkey, email/SMS). The heart of the app. | `shared/` |
| **`web/`** | The WebApp — the only HTTP-facing process. Routing, controllers, middleware, views. | `domain/`, `shared/` |
| **`worker/`** | The QueueWorker — a plain CLI process that listens on Valkey and does whatever shouldn't block an HTTP response: sending emails, texting customers, restocking inventory. | `domain/`, `shared/` |
| **`console/`** | One-off CLI tooling — migrations, staff management, anything run by hand rather than by a request. | `domain/`, `shared/` |
| **`tests/`** (root) | Only what no single deliverable can honestly claim alone — load, architecture, and security tests. | everything |

### Running the System (4 Containers)

Only **two core processes** run continuously alongside the infrastructure layer:

| Container / Service | Role |
| --- | --- |
| **WebApp** | Handles incoming web requests and user actions. |
| **QueueWorker** | Processes background tasks and events asynchronously. |
| **Postgres** | Primary database. |
| **Valkey** | Cache and Event broker. |

`console/` is not a standing process. To avoid idle container costs, admin and migration tasks execute on-demand inside the running `worker` container:

```bash
docker compose exec worker php console/main.php migrate
```

---

### Dependency Flow & Isolation

To keep components decoupled, dependencies flow strictly in **one direction**:

```bash
[ WebApp (web) ]      [ QueueWorker (worker) ]      [ Admin CLI (console) ]
       \                         |                         /
        +------------------------+------------------------+
                                 |
                                 v
                         [ Domain Layer ]
                                 |
                                 v
                         [ Shared Layer ]

```

* **`shared/`**: Base foundation (validators, exceptions, serializers).
* **`domain/`**: Core business logic (infrastructure, modules).
* **`web/`**, **`worker/`**, **`console/`**: Delivery mechanisms. They depend on `domain/`, but **never on each other**.

---

### Event-Driven Communication

Because `web/` and `worker/` do not import or talk to each other directly:

1. **`web/`** publishes an event payload to **Event Broker (Valkey)**.
2. **`worker/`** listens to Event Broker and processes the event.

Neither process knows the other exists. This strict separation is automatically enforced at build time by **Deptrac** and Composer package rules.

## The story of the architecture

Before anything was built, the actual requirements were written down and grouped into natural clusters: Appointments, Jobs, Billing, Inventory, Staff, and Identity. Each cluster represented a coherent area of responsibility—a **module**.

Modules aren't islands. Booking an appointment needs to know which vehicle it's for (`CustomerVehicle`), and completing a job requires checking `Inventory` and generating a bill in `Billing`. To share responsibilities cleanly, modules express their dependencies through **constructor injection**—receiving references to the other modules they need upon creation, never instantiating them directly.

Buried inside these modules was code that had nothing to do with business logic—opening database connections, publishing to queues, or checking caches. That code didn't know anything about jobs or invoices; it only knew how to talk to Postgres, Valkey, or SMTP. It was pulled out into its own layer: **Infrastructure**. The `domain/` package split cleanly into two parts: `Modules/` holding pure business logic, and `Infrastructure/` holding everything business logic depends on to interact with the outside world.

To prevent ten separate modules from opening ten redundant connections to the same database, the architecture needed a way to manage singletons without relying on anti-patterns like private constructors or global `getInstance()` methods.

Borrowing the core concept from microservice **service registries**, `IDomainRegistry` was created. It acts as a central lookup table, mapping service interfaces to small construction closures. The registry instantiates nothing up front. The first time a service is requested, the registry runs its closure, caches the resulting instance, and serves that same instance to every subsequent caller. Unused services cost nothing, and every component gets shared access to the exact same resources.

With `domain/` complete, three distinct delivery fronts were built around it, all accessing business logic through the exact same facades:

* **`web/` (WebApp):** Runs on FrankenPHP’s worker mode. It bootstraps the application once via `AppBuilder` and remains warm in memory. Incoming raw superglobals are converted into an immutable `Request` object, passed through a Trie-based `Router`, and sent through a nested middleware pipeline (`AuthMiddleware`, `RbacMiddleware`) to a Controller. The Controller delegates to a domain facade and returns a `Response`.
* **`worker/` (QueueWorker):** Handles asynchronous side effects like sending emails or processing payroll. Instead of making HTTP callers wait, domain facades publish small event payloads to Valkey. `QueueWorker` listens continuously to these channels and dispatches events to dedicated `EventHandler` classes.
* **`console/` (Console):** Serves manual operations like database migrations or admin tasks. It bypasses HTTP pipelines and event loops entirely, executing commands directly against domain facades or `IDatabase`.

### The HTTP Pipeline (`web/`)

A request hits the socket, FrankenPHP parses the raw bytes into PHP superglobals (`$_SERVER`, `$_GET`, `$_POST`), and the application immediately wraps them into a clean, immutable `Request` object. From that moment on, superglobals are dead to the codebase.

```bash
       [ Raw Bytes / Superglobals ]
                    |
                    v
          [ Immutable Request ]
                    |
                    v
            [ Trie Router ]
                    |
                    v
       +-------------------------+
       |   PIPELINE / MIDDLEWARE |
       |                         |
       |  [ AuthMiddleware ]     |
       |          |              |
       |  [ RbacMiddleware ]     |
       |          |              |
       |  [ ValidationStep ]     |
       +-------------------------+
                    |
                    v
             [ Controller ]
                    |
       +------------+------------+
       |                         |
       v                         v
[ Domain Facade ]       [ Return Response ]
       |
       +---> (Sync)  --> Postgres
       |
       +---> (Async) --> Valkey PubSub
```

The `Request` enters the **Trie Router**—a tree walked segment-by-segment to locate the target route. The router returns a pre-built execution pipeline where middleware wraps middleware, which ultimately wraps the controller action.

1. **`AuthMiddleware`** verifies the identity.
2. **`RbacMiddleware`** checks permissions.
3. **`ValidationStep`** checks the payload integrity.

If any layer rejects the request, execution short-circuits. If every layer agrees, control reaches the **Controller**. The controller’s job is deliberately thin: pull arguments from the `Request`, delegate work to a domain facade (`JobFacade`, `StaffFacade`), and wrap the result into an HTTP `Response`. It never interacts with storage or caches directly; the facade handles that through the registry.

---

### Asynchronous Offloading (`worker/`)

When a request triggers side effects that shouldn't delay the user—like sending SMS, processing notifications, or running accounting jobs—the domain facade publishes a lightweight event payload to **Valkey** and continues without waiting. The HTTP response drops back to the user immediately.

Behind the scenes, **`worker/` (QueueWorker)** operates as a dedicated process with no HTTP awareness:

```bash
       Valkey PubSub
             |
             v
      [ QueueWorker ]
             |
             v
      [ EventHandler ]
             |
             v
      [ Domain Facade ]

```

* It boots up, checks its registry for registered topics, and blocks while listening.
* When a message lands on Valkey, the worker routes it to a matched **`EventHandler`**.
* The handler calls directly into the target domain facade—the exact same facade a controller would use, executed from the queue instead of an HTTP socket.

---

### On-Demand Execution (`console/`)

Administrative actions, database migrations, seeding, manual triggers bypass HTTP pipelines, routing, and pub/sub loops entirely. **`console/`** boots directly, queries the registry for commands or core interfaces like `IDatabase`, and executes the work directly against the domain.

---

To maintain stability across all three fronts, a few core principles govern the entire codebase:

* **Explicit Wiring:** Dependencies are manually wired in `services/*.php` files—autowiring is explicitly avoided so missing dependencies fail deterministically.
* **Stateless Singletons:** Facades, controllers, and middleware carry no request-specific state, allowing them to sit safely in memory across persistent process loops.
* **Strict Error Taxonomy:** System failures and bugs extend from `VwrkError`, while expected business condition failures (such as payment declines) extend from `VwrkException`.

---

## `shared/` — generic, dependency-free utilities

The one test every file here has to pass: it carries **no** domain knowledge and **no** transport knowledge, regardless of how many packages use it. `IStaticTrie` is a good example, it backs `web/`'s router today, but it doesn't know what a route is; it could back a permission tree tomorrow without changing a line.

```text
shared/
├── src/
│   ├── Collections/
│   │   ├── IStaticTrie.php
│   │   └── TrieNode.php
│   ├── Serializers/
│   │   ├── IPayloadSerializer.php  # serialize(data): string / contentType(): string
│   │   ├── JsonSerializer.php
│   │   └── ProtobufSerializer.php
│   ├── Exceptions/
│   │   ├── VwrkError.php           # the code is wrong — never caught, just fixed
│   │   └── VwrkException.php       # the world didn't cooperate — caught and handled
│   └── Validators/                 # VIN, email, NIC, a generic Rule interface
├── composer.json                   # vwork/shared
└── tests/
```

## `domain/` — the business logic

Everything a real request cares about lives here: the modules (Job, Billing, Staff, ...) and the infrastructure they run on. Every module exposes exactly one public thing, a **facade** — nothing outside a module ever reaches into its internals, not another module, not `web/`, not `worker/`. Entities never leave a module either; a facade returns a real `Job` object, but nothing outside the module ever constructs one.

Every consumer of `domain/` reaches it through exactly one interface, `IDomainRegistry` — "give me this facade" or "give me this piece of infrastructure." Nothing more is exposed, and `domain/` never depends outward on anything except `shared/`.

`domain/` is one Composer package. `Infrastructure/` and `Modules/` are organizational folders inside it, not separate packages:

```text
domain/
├── src/
│   ├── IDomainRegistry.php               # the one door in
│   ├── Infrastructure/
│   │   ├── IInfrastructure.php           # connect() / reconnect()
│   │   ├── Database/
│   │   │   ├── IDatabase.php             # runTransaction(), query() — no entity knowledge at all
│   │   │   └── Database.php              # Postgres, via PDO
│   │   ├── PubSub/
│   │   │   ├── IPubSub.php
│   │   │   ├── ValkeyPubSub.php          # its own Redis connection
│   │   │   └── PubSubTopics.php          # every event the system can fire
│   │   ├── Cache/
│   │   │   ├── ICache.php
│   │   │   └── ValkeyCache.php           # its own Redis connection — never shared with PubSub
│   │   └── Notification/
│   │       ├── INotificationSender.php   # send(to, title, message)
│   │       ├── IEmailServer.php          # the generic SMTP primitive (attachments, etc.)
│   │       ├── EmailServer.php           # wraps PHPMailer — the only class that imports it
│   │       ├── EmailNotifier.php
│   │       └── SmsNotifier.php           # notify.lk
│   └── Modules/
│       ├── IFacade.php                   # empty marker — every module's public contract
│       ├── SystemConfig/
│       ├── Staff/
│       ├── CustomerVehicle/
│       ├── Identity/                     # requires: SystemConfig
│       ├── Appointment/                  # requires: CustomerVehicle, Staff
│       ├── Job/                          # requires: Staff, Billing, CustomerVehicle, Inventory
│       ├── Billing/
│       ├── Inventory/                    # requires: Supplier
│       ├── Supplier/                     # requires: Staff
│       └── Notification/                 # publishes to Valkey — delivery happens in worker/
├── composer.json                         # vwork/domain — requires: vwork/shared
└── tests/                                # Integration only — real Postgres + Valkey,
                                          # infrastructure and modules exercised together
```

Each module still follows the same internal shape it always did — a facade, an entity, an `Entity/`folder, and an `Internal/` folder nothing outside the module ever reaches into:

```text
domain/src/Modules/Job/
├── IJobFacade.php
├── JobFacade.php
├── Entity/
│   ├── Job.php
│   └── JobParts.php
└── Internal/
    ├── WorkflowService.php
    ├── StateService.php
    └── JobRepository.php
```

The dependency table above (`Identity` needs `SystemConfig`, `Appointment` needs `CustomerVehicle` and `Staff`, ...) is a discipline enforced by PHPat and code review, not by Composer — every module lives inside the same `vwork/domain` package, so Composer itself can't refuse to install one module without another.

A test proving "a module's facade actually works against real Postgres and real Valkey" is inherently testing the seam between `Infrastructure/` and `Modules/`, which is exactly what `domain/tests/` holds — real infrastructure, both halves exercised together, nothing faked. Anything faked (a module's facade against a fake `IDatabase`, `Database`'s reconnect logic against a fake `\PDO`) is a **Unit** test and lives right alongside the class it's testing, inside `domain/tests/` too, just never touching real Postgres or Valkey.

## `web/` — the WebApp

The only process that speaks HTTP. Everything here exists to turn a `Request` into a `Response`: match a route, run it through middleware, call a facade, shape the result.

```text
web/
├── src/
│   ├── IApp.php
│   ├── IAppBuilder.php
│   ├── WebApp.php
│   ├── AppBuilder.php
│   ├── IHttpRegistry.php              # getController() / getMiddleware()
│   ├── IServiceRegistry.php           # extends IDomainRegistry + IHttpRegistry
│   ├── AppServiceRegistry.php
│   ├── Http/                          # Request, Response, HttpMethods/Headers/Cookies
│   ├── Controllers/                   # IController, Controller (view/payload/sseEvent), + concrete
│   ├── Middleware/                    # IMiddleware, Auth/Rbac/Validation
│   ├── Router/                        # IRouter, Router, RouteMatch, RouteContext
│   ├── Pipeline/                      # IPipelineHandler/Factory, ControllerHandler, MiddlewareHandler
│   └── Utils/
│       ├── View.php
│       └── Csrf.php
├── resources/                         # not PHP source — sibling to src/, not inside it
│   ├── views/
│   ├── scss/
│   └── ts/
├── config/
│   ├── Caddyfile.dev
│   ├── Caddyfile.prod
│   ├── routes/                        # staff.php, customer.php
│   └── services/                      # infrastructure, facades, controllers, middleware
├── public/                            # web-server document root
│   ├── index.php                      # worker-mode entrypoint
│   └── index.dev.php                  # classic, single-call entrypoint
├── tests/
│   ├── Unit/                          # Router, Pipeline, AppServiceRegistry — everything faked
│   ├── Integration/                   # real Postgres/Valkey, a raw HTTP client — no browser involved
│   └── e2e/                           # real Postgres/Valkey, a real browser (Playwright) — the full depth
│                                      # of App alone, click to database. Not the same "e2e" as root tests/e2e —
│                                      # this one never involves worker/, it's web's own end-to-end
├── package.json                       # Bun — TS/SCSS build only
└── Dockerfile
```

Playwright's own dependencies live in `web/tests/e2e/package.json`, kept separate from the frontend build's `package.json` — one is a build-time dependency shipping to `public/assets/`, the other is test-only and never ships anywhere.

A controller's job is small and specific: read the request, call a facade with named arguments, hand the result to `view()`, `payload()`, or `sseEvent()`. It never touches Postgres, never touches Valkey directly.

## `worker/` — the QueueWorker

No HTTP anywhere in this process. It boots, subscribes to every topic it has a handler for, and blocks — reacting to messages as they arrive until the container stops it.

```text
worker/
├── src/
│   ├── IWorkerServiceRegistry.php     # extends IDomainRegistry, + getEventHandler()
│   ├── WorkerServiceRegistry.php
│   ├── QueueWorker.php
│   └── EventHandlers/
│       ├── IEventHandler.php
│       ├── JobCompletedNotificationHandler.php
│       └── InventoryLowStockNotificationHandler.php
├── config/
│   └── services/
│   │   ├── infrastructure.php         # the full set — this is what actually delivers notifications
│   │   ├── facades.php
│   │   └── eventHandlers.php          # keyed by PubSubTopics
│   └── php-cli.ini                       # opache config
├── tests/
│   ├── Unit/
│   └── Integration/                   # real Valkey — does QueueWorker actually react
├── Dockerfile                         # console/ shares this image
└── queue-worker                       # `php worker/queue-worker`
```

No third tier here — there's no browser, no rendered UI, nothing an `e2e/` folder would test that `Integration/` doesn't already cover as the deepest possible check on `Worker` alone.

## `console/` — one-off tooling

Migrations, creating a staff account by hand, sending a one-off reminder — anything a person runs deliberately rather than something a request or an event triggers. It has its own, complete set of bindings; it never assumes `worker/`'s bindings happen to cover what it needs.

```text
console/
├── src/
│   └── Commands/
│       ├── MigrateCommand.php            # talks to IDatabase directly — no facade, no schema yet
│       ├── StaffCreateCommand.php
│       └── NotifySendReminderCommand.php # calls the facade SYNCHRONOUSLY — nobody's waiting on a CLI command
├── config/
│   ├── services/
│   │   ├── infrastructure.php
│   │   └── facades.php
│   └── php-cli.ini                       # opache config
├── tests/
│   ├── Unit/
│   └── Integration/                      # real Postgres — does MigrateCommand produce the right schema
└── console                               # `php console/console migrate`
```

It has no `Dockerfile` of its own — it's built into `worker/`'s image and run with `docker compose exec worker php console/console <command>`.

## `tests/` (root) and `migrations/` — the things nobody owns

Everything in this folder passes one test: no single deliverable — not `domain/`, not `web/`, not `worker/`, not `console/` — could honestly claim it on its own.

```text
tests/
├── Load/            # k6 / Gatling — whole-stack performance under realistic concurrent traffic
├── Architecture/    # phpat / Deptrac — structural rules, checked across every package at once
└── Security/        # composer audit, secret scanning, dependency CVEs — whole-repo tooling,
                     # not "does AuthMiddleware work" (that's web/tests/Integration/'s job)

migrations/  # Ordered SQL. No single module owns the whole schema, so this can't live inside domain/.
```

## Getting started

```bash
git clone <repo-url> vwork && cd vwork
composer install
docker compose up -d                 # Postgres, Valkey, web, worker
docker compose exec worker php console/main.php migrate
```

| App | URL |
| --- | --- |
| Customer Web App | `http://localhost/customer` |
| Staff Web App | `http://localhost/staff` |

Admin isn't a separate app — it's a role, same as Technician or Supervisor, gated per-route.

## Checking your work

```bash
vendor/bin/phpunit --testsuite=unit           # every package's own Unit/
vendor/bin/phpunit --testsuite=integration     # domain/, web/, worker/, console/'s own Integration/
vendor/bin/phpunit --testsuite=e2e-app         # web/tests/e2e — Playwright, click to database, App alone
vendor/bin/phpat analyse                       # root tests/architecture — structural rules
composer audit                                  # root tests/security — dependency CVEs
vendor/bin/deptrac analyse --config-file=.tools/deptrac.php
vendor/bin/phpstan analyse --configuration=.tools/phpstan.neon
```

## Adding a new endpoint

1. Add the route in `web/config/routes/`.
2. Add the method to the module's facade interface, implement it, write a unit + integration test.
3. Add a controller action that calls the facade with named arguments.
4. Run Deptrac and PHPStan before you open a PR.

Routes live in `web/config/routes/*.php`, one file per surface (`staff.php`, `customer.php`), each returning a plain array — method, path, the controller action to call, the middleware chain, and which roles are allowed through:

```php
<?php
 
declare(strict_types=1);
 
use Vwork\Shared\Http\HttpMethods;
use Vwork\Domain\Modules\Identity\UserRoles;
use Vwork\Web\Controllers\JobController;
use Vwork\Web\Middleware\AuthMiddleware;
use Vwork\Web\Middleware\RbacMiddleware;
 
return [
    [
        'method' => HttpMethods::POST,
        'path' => '/staff/jobs/{id}/complete',
        'controller' => [
            'class' => JobController::class,
            'method' => 'complete',
        ]
        'middleware' => [AuthMiddleware::class, RbacMiddleware::class],
        'context' => [
            'roles' => [UserRoles::Technician, UserRoles::Supervisor],
            /* plus other route context information (cookie info, session info) */
        ]
    ],
];
```

`AppBuilder` reads every route file once, at bootstrap, and hands each one to `PipelineFactory` — which resolves `JobController`/`AuthMiddleware`/`RbacMiddleware` from the registry exactly once each, wraps them into one nested chain, and stores that chain directly at the route's leaf node in the router's trie. A request matching this path never re-resolves anything — it walks straight into the pre-built chain.

## Wiring a service

Every process builds its own registry from a stack of `config/services/*.php` files — plain scripts, each `return`ing a `class-string => closure` map. Nothing is autowired: if a binding is missing, `getFacade()`/`getInfrastructure()`/etc. throws a `VwrkError` naming exactly what's missing, right where it was asked for.

These files have no namespace of their own — they're `require`'d as configuration, not autoloaded as classes — but they freely `use` real classes from anywhere in `domain/`, `shared/`, or their own process.

**Infrastructure:**

```php
<?php
 
declare(strict_types=1);
 
use Vwork\Domain\Infrastructure\Database\IDatabase;
use Vwork\Domain\Infrastructure\Database\Database;
 
return [
    IDatabase::class => fn() => new Database(
        dsn: getenv('DB_DSN'),
        user: getenv('DB_USER'),
        pass: getenv('DB_PASSWORD'),
    ),
];
```

**Facade** — always constructed with whatever it needs, resolved back through the same registry it's being registered into:

```php
<?php
 
declare(strict_types=1);
 
use Vwork\Domain\IDomainRegistry;
use Vwork\Domain\Infrastructure\Database\IDatabase;
use Vwork\Domain\Modules\Job\IJobFacade;
use Vwork\Domain\Modules\Job\JobFacade;
 
return [
    IJobFacade::class => fn(IDomainRegistry $registry) => new JobFacade(
        db: $registry->getInfrastructure(IDatabase::class),
        staff: $registry->getFacade(IStaffFacade::class),
    ),
];
```

**Controller** (`web/`-only — needs `IHttpRegistry`'s facades wired up alongside `IDomainRegistry`'s):

```php
<?php
 
declare(strict_types=1);
 
use Vwork\Web\IServiceRegistry;
use Vwork\Domain\Modules\Job\IJobFacade;
use Vwork\Web\Controllers\JobController;
 
return [
    JobController::class => fn(IServiceRegistry $registry) => new JobController(
        jobs: $registry->getFacade(IJobFacade::class),
    ),
];
```

**Middleware** (`web/`-only):

```php
<?php
 
declare(strict_types=1);
 
use Vwork\Web\IServiceRegistry;
use Vwork\Domain\Modules\Identity\IIdentityFacade;
use Vwork\Web\Middleware\AuthMiddleware;
 
return [
    AuthMiddleware::class => fn(IServiceRegistry $registry) => new AuthMiddleware(
        identity: $registry->getFacade(IIdentityFacade::class),
    ),
];
```

**EventHandler** (`worker/`-only — keyed by `PubSubTopics`, not by class-string, since `IWorkerServiceRegistry::getEventHandler()` looks handlers up by topic):

```php
<?php
 
declare(strict_types=1);
 
use Vwork\Domain\IDomainRegistry;
use Vwork\Domain\Infrastructure\PubSub\PubSubTopics;
use Vwork\Domain\Modules\Notification\INotificationFacade;
use Vwork\Worker\EventHandlers\JobCompletedNotificationHandler;
 
return [
    PubSubTopics::JobCompleted->value => fn(IDomainRegistry $registry) => new JobCompletedNotificationHandler(
        notifier: $registry->getFacade(INotificationFacade::class),
    ),
];
```

Every one of these closures is lazy — none of them run until something actually asks the registry for that exact class or topic. Register a facade nobody ever calls, and it's never built at all.

## Still open

The architecture is settled; a few pieces are still just plans, not code:

* Route `{param}` extraction
* Reconnect logic for the database and cache clients
* Graceful shutdown for `QueueWorker::run()`
* The full command list for `console/`

## References

* Database Schema: `docs/ER.drawio`
* Use Case Diagrams: `docs/UseCases.drawio`
* Activiy Diagrams: `docs/Activities.drawio`
* State Diagrams: `docs/StateMachines.drawio`
* Architecture: `docs/adr/`.
