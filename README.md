# 🔧 vwork: Vehicle Workshop Management System

vwork runs a vehicle workshop: customers book appointments for their vehicles, and staff manage those appointments, jobs and payments. It's written in plain PHP with no framework, built by our team as a learning exercise in how web applications work underneath.

**Built with:** PHP 8.5 on FrankenPHP · PostgreSQL · Valkey · Bun (TypeScript + SCSS) · PHPUnit · Playwright

---

## 🚀 Quick start

**You need:**

- [Docker](https://docs.docker.com/get-docker/): on Windows and macOS, Docker Desktop (and it must be **running**)
- [VS Code](https://code.visualstudio.com/) with the **Dev Containers** extension
- *Optional:* a [Nerd Font](https://www.nerdfonts.com/) set as VS Code's terminal font, so the prompt's icons display

**Then:**

1. Clone the repository and open the folder in VS Code.
2. When VS Code offers to **Reopen in Container**, accept (or run *Dev Containers: Reopen in Container* from the command palette).
3. Wait for the first build to finish. It takes a few minutes the first time, and seconds after that.
4. Open **<http://localhost>**.

Something went wrong? Check [troubleshooting](docs/troubleshoot.md) first.

---

## 🧰 Everyday commands

Run these in the VS Code terminal. It's already inside the dev container.

| What | Command |
| --- | --- |
| Fix code style | `composer lint` |
| Check style + static analysis (what runs before a push) | `composer check` |
| Unit + integration tests | `composer test` |
| Browser (end-to-end) tests | `bun run test:web` |
| Build frontend assets | `bun run build:web` |

Most of these are also in VS Code under **Terminal → Run Task**.

Commands that manage the containers themselves run in a terminal **outside** the dev container, from the project folder:

| What | Command |
| --- | --- |
| Restart the dev server (FrankenPHP + asset watcher) | `docker compose -f .docker/docker-compose.dev.yml exec web sh web/dev.start.sh` |
| Run a console command | `docker compose -f .docker/docker-compose.dev.yml exec worker composer run:console -- <command>` |
| See the web server's logs | `docker compose -f .docker/docker-compose.dev.yml logs -f web` |

---

## 🗂️ What's where

```bash
vwork/
├── shared/        tools used by everything (logging, utilities)
├── domain/        business rules: appointments, customers, vehicles, payments
├── web/           the website: routes, controllers, views, frontend assets
├── worker/        background jobs (emails, SMS)
├── console/       admin commands (migrations and the like)
├── test/          the test container's Dockerfile
├── .docker/       Docker Compose files and environment settings
├── .devcontainer/ VS Code dev container setup
├── .tools/        settings for the linter, static analysis and tests
└── docs/          documentation
```

---

## 📚 Learn more

- **[How vwork works](docs/architecture.md)**: the whole system, told as one appointment's journey
- **[Development guide](docs/development.md)**: Docker, testing, debugging, code quality
- **[Decisions](docs/decisions.md)**: what we chose, and why
- **[Troubleshooting](docs/troubleshooting.md)**: known problems and their fixes
