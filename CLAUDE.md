# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Overview

URL shortener API built with **Symfony 7.3** on **PHP 8.3+**, running on the **FrankenPHP** runtime in Docker, backed by **PostgreSQL** (Doctrine ORM) and **Redis** (Predis). Authentication is JWT-based (`firebase/php-jwt`). The codebase follows **CQRS** inside a **modular monolith** with **DDD** layering.

## Commands

The app runs inside Docker; prefix the commands below with `docker compose exec php` when running against the container, or run them directly if you have a local PHP 8.3+ toolchain.

- **Start the stack (dev):** `docker compose up -d` — uses `compose.yaml` + `compose.override.yaml`, building the `frankenphp_dev` target with file-watching workers and bind-mounted source. Set `XDEBUG_MODE` to enable Xdebug.
- **Run all tests:** `php bin/phpunit` (or `vendor/bin/phpunit`). PHPUnit is configured strict — it **fails on any deprecation, notice, or warning** (`phpunit.dist.xml`). Tests run under `APP_ENV=test` against an in-memory SQLite DB (`.env.test`).
- **Run a single test:** `php bin/phpunit --filter testCreateShortLinkReturnsId tests/Controller/ShortLinkControllerTest.php`
- **Symfony console:** `php bin/console <command>` (e.g. `php bin/console debug:router`, `debug:container`).
- **Migrations:** `php bin/console doctrine:migrations:migrate`. Generate with `doctrine:migrations:diff` (migration classes live in `migrations/`, not under `modules/`).
- **Production image:** built from the `frankenphp_prod` Dockerfile target with `compose.prod.yaml`.

## Architecture

### Two PSR-4 roots

`composer.json` maps two namespaces:
- `App\` → `src/` — the **framework / delivery layer** (controllers, HTTP request DTOs, Kernel, the concrete Messenger bus binding).
- `Module\` → `modules/` — the **domain**, split into bounded contexts: `User`, `ShortLink`, and `Shared`.

Keep framework concerns in `src/` and domain logic in `modules/`. Controllers translate HTTP into commands/queries and never contain business rules.

### Module layering (DDD)

Each module under `modules/<Context>/` is split into:
- `Application/` — the domain core: `Entities/` (Doctrine-mapped via attributes), `Commands/`, `Queries/` (interfaces), `Services/`, `Repositories/` (interfaces), `Events/`, `Listeners/`, `Exceptions/`.
- `Infrastructure/` — concrete adapters: `Doctrine/` (repository + query implementations), `Cache/`, `Security/`, framework `Listeners/`.

Application code depends on **interfaces** it owns; Infrastructure provides the implementations. Symfony autowires interface → single implementation automatically (see `config/services.yaml`, which loads both `App\` and `Module\` as service resources).

### CQRS — write side (Commands)

- Commands implement `Module\Shared\Application\Command\Command`; handlers implement `Module\Shared\Application\Command\CommandHandler`.
- The `_instanceof` rule in `config/services.yaml` auto-tags every `CommandHandler` as a `messenger.message_handler` on the **`command.bus`** (the default Messenger bus, `config/packages/messenger.yaml`). The bus runs `doctrine_transaction` middleware, so each command handler executes in a DB transaction.
- Controllers depend on the `Module\Shared\Application\Command\CommandBus` interface. The concrete `App\Shared\Infrastructure\Messenger\CommandBus` wraps Symfony's `MessageBusInterface` and **unwraps** `HandlerFailedException` to rethrow the original domain exception.
- Flow: `src/Requests/.../*.php` (`::fromHttp(Request)` → `->toCommand()`) → `$commandBus->dispatch($command)` → handler.

### CQRS — read side (Queries)

Queries do **not** go through the bus. A query interface lives in `<Module>/Application/Queries/`, its Doctrine implementation in `<Module>/Infrastructure/Doctrine/Queries/`, and controllers inject the interface directly. Read implementations use the query builder returning array/DTO results (`Application/Queries/.../*DTO.php`), bypassing entity hydration.

### Domain events

Handlers dispatch events (e.g. `ShortLinkCreated`, `ShortLinkAccessed`) via Symfony's `EventDispatcherInterface`. Listeners are wired **manually** in `config/services.yaml` with `kernel.event_listener` tags — adding a listener means both writing the class and registering the tag there (autoconfigure does not pick these up). Caching of links and click-counter increments are implemented as listeners.

### Security

Stateless JWT firewalls (`config/packages/security.yaml`):
- `^/api/login_check` — `json_login` with `email`/`password`, handled by `Module\User\Infrastructure\Security\LoginSuccessHandler` (issues the token).
- `^/api/register` — open (`security: false`).
- `^/api` — `access_token` auth validated by `Module\User\Infrastructure\Security\AccessTokenHandler`.

The user provider loads `Module\User\Infrastructure\Doctrine\Entities\User` by `email`.

### Persistence & caching

- Doctrine ORM mappings are declared per-module in `config/packages/doctrine.yaml` (`mappings:` → `modules/<Context>/Application/Entities`, attribute-driven, `is_bundle: false`). A new module's entities must be added there.
- PostgreSQL in dev/prod; in-memory SQLite for tests.
- Default cache uses array adapters out of the box (`config/packages/cache.yaml`); Redis (`predis`) is available via `REDIS_URL` for the cache repositories.

## Testing notes

- Controller tests extend `WebTestCase` and override services in the test container — e.g. they replace the `CommandBus` with an anonymous stub via `static::getContainer()->set(CommandBus::class, ...)` to avoid dispatching through Messenger. Prefer this interface-substitution approach over hitting real infrastructure.
