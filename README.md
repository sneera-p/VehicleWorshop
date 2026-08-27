# vwork — Vehicle Workshop Management System

A pure PHP modular monolith for small-to-medium vehicle workshops (≤200 staff, ~2,000 active customers). No framework, no ORM, no HTMX — routing, DI, and the request pipeline are all hand-rolled, on purpose, so the team learns PHP from first principles instead of a framework's opinions.

## The shape of the system

`vwork` is organized into six top-level folders, each holding one or more small Composer packages with a clear, single job:

| Folder | What it is | Depends on |
| --- | --- | --- |
| **`shared/`** | Small, dependency-free utilities — a generic trie, the exception hierarchy, validators. Zero domain knowledge, zero HTTP knowledge. | nothing |
| **`domain/`** | All business logic. Every module (Job, Billing, Staff, ...) and the infrastructure they run on (Postgres, Valkey, email/SMS). The heart of the app. | `shared/` |
| **`app/`** | The WebApp — the only HTTP-facing process. Routing, controllers, middleware, views. | `domain/`, `shared/` |
| **`worker/`** | The QueueWorker — a plain CLI process that listens on Valkey and does whatever shouldn't block an HTTP response: sending emails, texting customers, restocking inventory. | `domain/`, `shared/` |
| **`console/`** | One-off CLI tooling — migrations, staff management, anything run by hand rather than by a request. | `domain/`, `shared/` |
| **`tests/`** (root) | Only what no single deliverable can honestly claim alone — cross-process, load, architecture, and security tests. | everything |

Only two processes run continuously: **WebApp** and **QueueWorker**, alongside Postgres and Valkey — four containers, total. `console/` isn't a fifth — it's executed on demand *inside* `worker`'s already-running container (`docker compose exec worker php console/console migrate`), since it's used rarely enough that giving it its own standing container would be pure idle cost.

`domain/` is the layer everything else depends on. It depends on `shared/` — its facades throw `VwrkException`, its input gets checked against `shared/`'s validators — but never on `app/`, `worker/`, or `console/`. That one-directional rule, enforced by Deptrac and by which packages each `composer.json` is allowed to `require`, is what lets `app/` publish an event to Valkey without knowing `worker/` exists, and lets `worker/` react to it without knowing `app/` exists. Neither process ever talks to the other directly.

`vwork` is exactly five Composer packages — `shared/`, `domain/`, `app/`, `worker/`, `console/` — each its own `composer.json`, its own `src/`, its own `tests/`. `Infrastructure/` and `Modules/` inside `domain/`, and `Collections/`/`Serializers/`/`Exceptions/`/`Validators/` inside `shared/`, are organizational folders, not separate packages — nothing in the whole tree has ever needed one piece of `domain/` or `shared/` without the rest.

## The story of the architecture

### It starts with requirements, not code

Before anything was built, the actual requirements — what a workshop needs to track, who does what, which workflows exist — were written down and grouped. Appointments, Jobs, Billing, Inventory, Staff, Identity, and so on fell out of that grouping naturally: each cluster of requirements was really describing one coherent area of responsibility. Those clusters became **modules** — Job, Billing, Staff, Identity, Appointment, CustomerVehicle, Inventory, Supplier, SystemConfig, Notification.

### Modules need each other

It didn't take long to notice that modules aren't islands. Booking an appointment needs to know which vehicle it's for — that's `CustomerVehicle`'s job, not `Appointment`'s. Completing a job needs to check inventory and generate a bill — that's `Inventory` and `Billing`'s job. So modules ended up depending on other modules, and the natural way to express that in PHP is **constructor injection** — a module receives references to the other modules it needs, passed in when it's built, never reaching out and constructing them itself.

### Modules were hiding something else

Looking closer at what was going *inside* each module, a second pattern showed up: buried in the business logic was code that had nothing to do with the business at all — opening a database connection, publishing to a queue, checking a cache. That code didn't know anything about jobs or invoices; it only knew how to talk to Postgres, or Valkey, or an SMTP server. It didn't belong inside the modules — it was a different kind of thing entirely.

So it was pulled out and given its own home: **Infrastructure**. Same constructor-injection idea applies here too — a module doesn't create its own database connection, it receives one. That split is what `domain/` actually is: `Modules/` holds the business logic, `Infrastructure/` holds everything that business logic depends on but isn't itself business logic.

### One connection, not ten

Here's where a real problem showed up. If every module that needed a database connection just created its own, a workshop with ten modules would end up with ten separate connections to the same Postgres instance — wasteful, and worse, meaningless: there's only ever *one* database. The same is true the other way around — if three different modules all needed `StaffFacade`, you don't want three separate `StaffFacade` objects floating around, each with no idea the others exist.

What was actually needed was something **singleton-like** — one instance of each service, built once, handed out to whoever asks for it. Not the textbook Singleton pattern (a private constructor and a `getInstance()` call baked into the class itself) — that hides a dependency and makes testing painful. What was needed was a place *outside* the classes themselves that could hand out one shared instance, on request.

### Borrowing an idea from microservices

Microservice architectures solve a related problem with a **service registry** — a central place a service can ask "where do I find the Inventory service" and get back a live reference to talk to. `vwork` isn't microservices — there's no network involved, no separate deployables per module — but the underlying idea transfers cleanly: one place you go to ask for "the Database" or "the Staff facade," and it hands you the one, shared instance.

That's the `IDomainRegistry`. Internally, it holds a table — every service it can provide, keyed by its interface, paired with a small closure that knows how to build it. The registry doesn't build anything up front. The first time something asks for `IDatabase`, the registry runs that one closure, keeps the result, and hands out that same result to everyone who asks afterward. Ask for something nobody ever needs, and it never gets built at all — the closures just sit there, unused and free.

That lazy, build-once, share-forever mechanism is what makes the singleton-like property actually work, without a single private constructor anywhere in the codebase.

With that in place, `domain/` was complete: modules for the business logic, infrastructure for everything external, a registry tying them together, one instance of everything, built only when needed.

### Now something has to answer a request

None of this does anything on its own — it needs a front door. That's `app/`, the WebApp.

FrankenPHP (the server this whole thing runs on) has a mode built specifically for PHP: **worker mode**. Instead of the old PHP model — spin up a fresh process, run the script top to bottom, throw it all away, repeat for the next request — a worker-mode process stays alive. It runs your bootstrap code exactly once, and then hands you a loop: give me a function, and I'll call it, once per incoming request, forever, reusing everything you built the first time.

That splits the whole app into exactly two stages:

1. **Build the app** — once. Assemble the registry, build the router, wire everything together.
2. **Handle a request** — over and over. Given the thing built in stage one, take one request in, produce one response out.

So `AppBuilder` exists to do stage one, and it hands back something implementing `IApp` — an object with exactly one method that matters for stage two: `getRunner()`, which returns the function FrankenPHP calls, over and over, for the rest of the process's life.

### What actually happens to a request

A request arrives as raw bytes on a socket — FrankenPHP turns that into PHP's old, familiar superglobals: `$_SERVER`, `$_GET`, `$_POST`. The first thing the handler does is take all of that scattered, untyped data and build one clean, immutable `Request` object out of it — the method, the path, the headers, the cookies, the body — so that nothing downstream ever has to touch a superglobal again.

That `Request` gets handed to the `Router`, which was built once, back in stage one, as a trie — a tree that can be walked one path segment at a time. Walking it tells you exactly one thing: which pre-built chain of handlers is responsible for this exact path and method.

That chain is where **Controllers**, **Middleware**, and the **Pipeline** meet. Every route was registered with a controller action and a list of middleware — `AuthMiddleware`, maybe `RbacMiddleware`, maybe a validation step. At build time, the pipeline wrapped all of that into one nested structure: the first middleware wraps the second, which wraps the third, which finally wraps the controller action itself. Walking into that structure runs the first middleware; it decides whether to reject the request outright or pass it along to the next layer. Only if every layer agrees does the request ever reach the controller.

The controller's job is deliberately small: pull whatever it needs out of the `Request`, call a facade — `JobFacade`, `StaffFacade`, whichever module owns this piece of business logic — and turn whatever comes back into a `Response`. It never touches Postgres, never touches Valkey. It doesn't need to; the facade already knows how, through the registry.

### Worker doesn't wait for anyone

Some things a request triggers shouldn't make the person waiting for a response wait even longer — sending an email, texting a customer, updating a stock count. Those get handed off instead of done inline: the facade publishes a small message to Valkey and moves on, and the HTTP response goes out immediately, notification or not.

Something still has to actually send that email, though — that's `worker/`, the QueueWorker. It has no HTTP in it at all; it boots, asks its own registry which topics it has handlers for, and blocks, listening. When a message arrives, it's handed to the matching `EventHandler` — a small class that knows what to do with one specific kind of event, usually just calling straight into a facade, the same one a controller might have called, just from the other side of a queue instead of a browser.

### Console is the same idea, simplified further

Not everything is triggered by a browser or an event, though — sometimes a person just needs to run something by hand: apply a database migration, create a staff account, fire off a one-off reminder. That's `console/`. It's the smallest of the three: no routing, no middleware, no pub/sub loop — just a registry and a handful of commands, each one calling straight into a facade or, for something like a migration, straight into `IDatabase` itself, since there's no schema yet for a facade to assume.

Three different fronts — one waits on a browser, one waits on a queue, one waits on a person typing a command — and every single one of them reaches the exact same business logic through the exact same door.

## A few decisions worth knowing before you touch the code

**Nothing is autowired.** Every dependency is wired by hand in a `services/*.php` file. If something's missing, you get a clear error naming exactly what's missing — not a mysterious failure three calls later.

**Almost everything is a singleton.** Facades, controllers, middleware — one instance each, for the life of the process, because none of them hold onto any data between requests. Database and cache connections reconnect automatically if they ever drop, and each piece of infrastructure (database, cache, pub/sub) gets its own connection — nothing is shared between them.

**Facades take and return plain, typed arguments — never bundled request objects.** A controller translates raw request data into named arguments once, at the point it calls a facade. Facades return real domain entities directly; there's no separate DTO layer duplicating every entity's shape.

**Two kinds of failure.** Anything descending from `VwrkError` means the code itself is broken — a missing config, a bug. Anything descending from `VwrkException` means the world didn't cooperate — a failed payment, a bad input — and the app is expected to handle it gracefully.

**Work that can wait, waits.** Marking a job complete triggers a notification, but the web request never waits for that email to send — it drops a message on Valkey and moves on. `QueueWorker` does the slow part later, out of the customer's way. A console command calling the same facade directly has no such concern, since nobody's waiting on it — it can call the notification sender synchronously.

---

## `shared/` — generic, dependency-free utilities

The one test every file here has to pass: it carries **no** domain knowledge and **no** transport knowledge, regardless of how many packages use it. `IStaticTrie` is a good example — it backs `app/`'s router today, but it doesn't know what a route is; it could back a permission tree tomorrow without changing a line.

```text
shared/
├── src/
│   ├── Collections/
│   │   ├── IStaticTrie.php
│   │   └── TrieNode.php
│   ├── Serializers/
│   │   ├── IPayloadSerializer.php   # serialize(data): string / contentType(): string
│   │   ├── JsonSerializer.php
│   │   └── ProtobufSerializer.php
│   ├── Exceptions/
│   │   ├── VwrkError.php             # the code is wrong — never caught, just fixed
│   │   └── VwrkException.php         # the world didn't cooperate — caught and handled
│   └── Validators/                    # VIN, email, NIC, a generic Rule interface
├── composer.json                       # vwork/shared
└── tests/
```

## `domain/` — the business logic

Everything a real request cares about lives here: the modules (Job, Billing, Staff, ...) and the infrastructure they run on. Every module exposes exactly one public thing, a **facade** — nothing outside a module ever reaches into its internals, not another module, not `app/`, not `worker/`. Entities never leave a module either; a facade returns a real `Job` object, but nothing outside the module ever constructs one.

Every consumer of `domain/` reaches it through exactly one interface, `IDomainRegistry` — "give me this facade" or "give me this piece of infrastructure." Nothing more is exposed, and `domain/` never depends outward on anything except `shared/`.

`domain/` is one Composer package. `Infrastructure/` and `Modules/` are organizational folders inside it, not separate packages:

```text
domain/
├── src/
│   ├── IDomainRegistry.php            # the one door in
│   ├── IFacade.php                     # empty marker — every module's public contract
│   ├── Infrastructure/
│   │   ├── IInfrastructure.php         # connect() / reconnect()
│   │   ├── Database/
│   │   │   ├── IDatabase.php            # runTransaction(), query() — no entity knowledge at all
│   │   │   └── Database.php              # Postgres, via PDO
│   │   ├── PubSub/
│   │   │   ├── IPubSub.php
│   │   │   ├── ValkeyPubSub.php          # its own Redis connection
│   │   │   └── PubSubTopics.php          # every event the system can fire
│   │   ├── Cache/
│   │   │   ├── ICache.php
│   │   │   └── ValkeyCache.php            # its own Redis connection — never shared with PubSub
│   │   └── Notification/
│   │       ├── INotificationSender.php    # send(to, title, message)
│   │       ├── IEmailServer.php            # the generic SMTP primitive (attachments, etc.)
│   │       ├── EmailServer.php              # wraps PHPMailer — the only class that imports it
│   │       ├── EmailNotifier.php
│   │       └── SmsNotifier.php               # notify.lk
│   └── Modules/
│       ├── SystemConfig/
│       ├── Staff/
│       ├── CustomerVehicle/
│       ├── Identity/                    # requires: SystemConfig
│       ├── Appointment/                 # requires: CustomerVehicle, Staff
│       ├── Job/                         # requires: Staff, Billing, CustomerVehicle, Inventory
│       ├── Billing/
│       ├── Inventory/                   # requires: Supplier
│       ├── Supplier/                    # requires: Staff
│       └── Notification/                 # publishes to Valkey — delivery happens in worker/
├── composer.json                        # vwork/domain — requires: vwork/shared
└── tests/                                # Integration only — real Postgres + Valkey,
                                            # infrastructure and modules exercised together
```

Each module still follows the same internal shape it always did — a facade, an entity, and an `Internal/` folder nothing outside the module ever reaches into:

```text
domain/src/Modules/Job/
├── JobFacade.php
├── Job.php                    # the entity — never leaves this module
└── Internal/
    ├── WorkflowService.php
    ├── StateService.php
    └── JobRepository.php
```

The dependency table above (`Identity` needs `SystemConfig`, `Appointment` needs `CustomerVehicle` and `Staff`, ...) is a discipline enforced by Deptrac and code review, not by Composer — every module lives inside the same `vwork/domain` package, so Composer itself can't refuse to install one module without another.

A test proving "a module's facade actually works against real Postgres and real Valkey" is inherently testing the seam between `Infrastructure/` and `Modules/`, which is exactly what `domain/tests/` holds — real infrastructure, both halves exercised together, nothing faked. Anything faked (a module's facade against a fake `IDatabase`, `Database`'s reconnect logic against a fake `\PDO`) is a **Unit** test and lives right alongside the class it's testing, inside `domain/tests/` too, just never touching real Postgres or Valkey.

## `app/` — the WebApp

The only process that speaks HTTP. Everything here exists to turn a `Request` into a `Response`: match a route, run it through middleware, call a facade, shape the result.

```text
app/
├── src/
│   ├── IApp.php / IAppBuilder.php / WebApp.php / AppBuilder.php
│   ├── IHttpRegistry.php             # getController() / getMiddleware()
│   ├── IServiceRegistry.php           # extends IDomainRegistry + IHttpRegistry
│   ├── AppServiceRegistry.php
│   ├── Http/                          # Request, Response, HttpMethods/Headers/Cookies
│   ├── Controllers/                   # IController, Controller (view/payload/sseEvent), + concrete
│   ├── Middleware/                    # IMiddleware, Auth/Rbac/Validation
│   ├── Router/                        # IRouter, Router, RouteMatch, RouteContext
│   ├── Pipeline/                      # IPipelineHandler/Factory, ControllerHandler, MiddlewareHandler
│   └── Utils/
│       └── View.php
├── resources/                        # not PHP source — sibling to src/, not inside it
│   ├── views/  ├── scss/  └── ts/
├── config/
│   ├── Caddyfile / Caddyfile.prod
│   ├── routes/                        # staff.php, customer.php
│   └── services/                       # infrastructure, facades, controllers, middleware
├── public/                            # web-server document root
│   ├── index.php                       # worker-mode entrypoint
│   └── index.dev.php                    # classic, single-call entrypoint
├── tests/
│   ├── Unit/          # Router, Pipeline, AppServiceRegistry — everything faked
│   ├── Integration/    # real Postgres/Valkey, a raw HTTP client — no browser involved
│   └── e2e/              # real Postgres/Valkey, a real browser (Playwright) — the full depth
│                          # of App alone, click to database. Not the same "e2e" as root tests/e2e —
│                          # this one never involves worker/, it's App's own end-to-end
├── package.json                        # Bun — TS/SCSS build only
└── Dockerfile
```

Playwright's own dependencies live in `app/tests/e2e/package.json`, kept separate from the frontend build's `package.json` — one is a build-time dependency shipping to `public/assets/`, the other is test-only and never ships anywhere.

A controller's job is small and specific: read the request, call a facade with named arguments, hand the result to `view()`, `payload()`, or `sseEvent()`. It never touches Postgres, never touches Valkey directly.

## `worker/` — the QueueWorker

No HTTP anywhere in this process. It boots, subscribes to every topic it has a handler for, and blocks — reacting to messages as they arrive until the container stops it.

```text
worker/
├── src/
│   ├── IWorkerServiceRegistry.php    # extends IDomainRegistry, + getEventHandler()
│   ├── WorkerServiceRegistry.php
│   ├── QueueWorker.php
│   └── EventHandlers/
│       ├── IEventHandler.php
│       ├── JobCompletedNotificationHandler.php
│       └── InventoryLowStockNotificationHandler.php
├── config/
│   └── services/
│       ├── infrastructure.php         # the full set — this is what actually delivers notifications
│       ├── facades.php
│       └── eventHandlers.php           # keyed by PubSubTopics
├── tests/
│   ├── Unit/
│   └── Integration/                    # real Valkey — does QueueWorker actually react
├── Dockerfile                          # console/ shares this image
└── queue-worker                        # `php worker/queue-worker`
```

No third tier here — there's no browser, no rendered UI, nothing an `e2e/` folder would test that `Integration/` doesn't already cover as the deepest possible check on `Worker` alone.

## `console/` — one-off tooling

Migrations, creating a staff account by hand, sending a one-off reminder — anything a person runs deliberately rather than something a request or an event triggers. It has its own, complete set of bindings; it never assumes `worker/`'s bindings happen to cover what it needs.

```text
console/
├── src/
│   └── Commands/
│       ├── MigrateCommand.php           # talks to IDatabase directly — no facade, no schema yet
│       ├── StaffCreateCommand.php
│       └── NotifySendReminderCommand.php # calls the facade SYNCHRONOUSLY — nobody's waiting on a CLI command
├── config/
│   └── services/
│       ├── infrastructure.php
│       └── facades.php
├── tests/
│   ├── Unit/
│   └── Integration/                     # real Postgres — does MigrateCommand produce the right schema
└── console                              # `php console/console migrate`
```

It has no `Dockerfile` of its own — it's built into `worker/`'s image and run with `docker compose exec worker php console/console <command>`.

## `tests/` (root) and `migrations/` — the things nobody owns

Everything in this folder passes one test: no single deliverable — not `domain/`, not `app/`, not `worker/`, not `console/` — could honestly claim it on its own.

```text
tests/
├── e2e/            # PHPUnit, not Playwright. Proves App → Worker actually works when BOTH are
│                     # running at once — App publishes a real event, Worker really reacts to it.
│                     # Asserts on an observable side effect (a DB row, a caught test email),
│                     # never reaches into worker/'s process directly.
├── load/            # k6 / Gatling — whole-stack performance under realistic concurrent traffic
├── architecture/     # phpat / Deptrac — structural rules, checked across every package at once
└── security/          # composer audit, secret scanning, dependency CVEs — whole-repo tooling,
                        # not "does AuthMiddleware work" (that's app/tests/Integration/'s job)

migrations/  # Ordered SQL. No single module owns the whole schema, so this can't live inside domain/.
```

## Getting started

```bash
git clone <repo-url> vwork && cd vwork
composer install
docker compose up -d                 # Postgres, Valkey, app, worker
docker compose exec worker php console/console migrate
```

| App | URL |
| --- | --- |
| Customer Web App | `http://localhost/customer` |
| Staff Web App | `http://localhost/staff` |

Admin isn't a separate app — it's a role, same as Technician or Supervisor, gated per-route.

## Checking your work

```bash
vendor/bin/phpunit --testsuite=unit           # every package's own Unit/
vendor/bin/phpunit --testsuite=integration     # domain/, app/, worker/, console/'s own Integration/
vendor/bin/phpunit --testsuite=e2e-app         # app/tests/e2e — Playwright, click to database, App alone
vendor/bin/phpunit --testsuite=e2e             # root tests/e2e — App publishes, Worker actually reacts
vendor/bin/phpat analyse                       # root tests/architecture — structural rules
composer audit                                  # root tests/security — dependency CVEs
vendor/bin/deptrac analyse --config-file=.tools/deptrac.php
vendor/bin/phpstan analyse --configuration=.tools/phpstan.neon
```

## Adding a new endpoint

1. Add the route in `app/config/routes/`.
2. Add the method to the module's facade interface, implement it, write a unit + integration test.
3. Add a controller action that calls the facade with named arguments.
4. Run Deptrac and PHPStan before you open a PR.

## Still open

The architecture is settled; a few pieces are still just plans, not code:

- Route `{param}` extraction
- Reconnect logic for the database and cache clients
- Graceful shutdown for `QueueWorker::run()`
- The full command list for `console/`

## Where the decisions came from

Every module and workflow traces back to `docs/ER.drawio`, `docs/UseCases.drawio`, `docs/Activities.drawio`, and `docs/StateMachines.drawio`. The reasoning behind the architecture itself is written up in `docs/adr/`.
