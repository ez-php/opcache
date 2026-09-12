# Coding Guidelines

Applies to the entire ez-php project — framework core, all modules, and the application template.

---

## Environment

- PHP **8.5**, Composer for dependency management
- All project based commands run **inside Docker** — never directly on the host

```
docker compose exec app <command>
```

Container name: `ez-php-app`, service name: `app`.

---

## Quality Suite

Run after every change:

```
docker compose exec app composer full
```

Executes in order:
1. `sync_guidelines.php --check` — fails if any `CLAUDE.md` has drifted from this file
2. `check_test_classes.php` — fails on a duplicate test class name (all packages share the `Tests\` namespace, so a collision is a fatal error in the aggregated run, not a test failure)
3. `phpstan analyse` — static analysis, level 9, config: `phpstan.neon`
4. `php-cs-fixer fix` — auto-fixes style (`@PSR12` + `@PHP83Migration` + strict rules)
   *(Note: `@PHP85Migration` does not exist yet in php-cs-fixer; `@PHP83Migration` is the highest available and is used intentionally even though the project targets PHP 8.5)*
5. `phpunit` — all tests with coverage

Individual commands when needed:
```
composer analyse             # PHPStan only
composer cs                  # CS Fixer only
composer test                # PHPUnit only
composer guidelines:check    # CLAUDE.md drift only
composer test-classes:check  # duplicate test class names only
```

**PHPStan:** never suppress with `@phpstan-ignore-line` — always fix the root cause.

---

## Coding Standards

- `declare(strict_types=1)` at the top of every PHP file
- Typed properties, parameters, and return values — avoid `mixed`
- PHPDoc on every class and public method
- One responsibility per class — keep classes small and focused
- Constructor injection — no service locator pattern
- No global state unless intentional and documented

**Naming:**

| Thing | Convention |
|---|---|
| Classes / Interfaces | `PascalCase` |
| Methods / variables | `camelCase` |
| Constants | `UPPER_CASE` |
| Files | Match class name exactly |

**Principles:** SOLID · KISS · DRY · YAGNI

---

## Workflow & Behavior

- Write tests **before or alongside** production code (test-first)
- Read and understand the relevant code before making any changes
- Modify the minimal number of files necessary
- Keep implementations small — if it feels big, it likely belongs in a separate module
- No hidden magic — everything must be explicit and traceable
- No large abstractions without clear necessity
- No heavy dependencies — check if PHP stdlib suffices first
- Respect module boundaries — don't reach across packages
- Keep the framework core small — what belongs in a module stays there
- Document architectural reasoning for non-obvious design decisions
- Do not change public APIs unless necessary
- Prefer composition over inheritance — no premature abstractions

---

## New Modules & CLAUDE.md Files

### 1 — Required files

Every module under `modules/<name>/` must have:

| File | Purpose |
|---|---|
| `composer.json` | package definition, deps, autoload |
| `phpstan.neon` | static analysis config, level 9 |
| `phpunit.xml` | test suite config |
| `.php-cs-fixer.php` | code style config |
| `.gitignore` | ignore `vendor/`, `.env`, cache |
| `.env.example` | environment variable defaults (copy to `.env` on first run) |
| `docker-compose.yml` | Docker Compose service definition (always `container_name: ez-php-<name>-app`) |
| `docker/app/Dockerfile` | module Docker image (`FROM au9500/php:8.5`) |
| `docker/app/container-start.sh` | container entrypoint: `composer install` → `sleep infinity` |
| `docker/app/php.ini` | PHP ini overrides (`memory_limit`, `display_errors`, `xdebug.mode`) |
| `.github/workflows/ci.yml` | standalone CI pipeline |
| `README.md` | public documentation |
| `tests/TestCase.php` | base test case for the module |
| `start.sh` | convenience script: copy `.env`, bring up Docker, wait for services, exec shell |
| `CLAUDE.md` | see section 2 below |

### 2 — CLAUDE.md structure

Every module `CLAUDE.md` must follow this exact structure:

1. **Full content of `CODING_GUIDELINES.md`, verbatim** — copy it as-is, do not summarize or shorten
2. A `---` separator
3. `# Package: ez-php/<name>` (or `# Directory: <name>` for non-package directories)
4. Module-specific section covering:
   - Source structure — file tree with one-line description per file
   - Key classes and their responsibilities
   - Design decisions and constraints
   - Testing approach and infrastructure requirements (MySQL, Redis, etc.)
   - What does **not** belong in this module

**Do not edit part 1 by hand.** It is generated from `CODING_GUIDELINES.md` by
`sync_guidelines.php` at the project root:

```
php sync_guidelines.php            # rewrite every out-of-sync CLAUDE.md
php sync_guidelines.php --check    # report drift, exit 1 if any (CI / pre-commit)
```

Edit `CODING_GUIDELINES.md`, then run the script — it replaces everything before the
`# Package:` / `# Directory:` / `# Project:` heading and preserves the hand-written
section below it byte-for-byte. Editing a single copy only creates drift; before this
script existed, all 40 copies had diverged.

### 3 — Scaffolding a new module

`make_module.php` at the project root writes the required-file set and the monorepo
wiring in one step, wrapping `docker-init` for the Docker subset:

```
composer module:make <name> -- --description="..."
php make_module.php <name> --description="..." --services=mysql,redis
```

`<name>` is the kebab-case package name; the namespace is derived as
`EzPhp\<PascalCase>` unless `--namespace=` overrides it (`bignum` → `BigNum` and
`opcache` → `OPCache` are existing exceptions the guess gets wrong).

It writes `modules/<name>/` and registers the module in the four places the monorepo
needs it — root `composer.json` (`autoload.psr-4`), `phpstan.neon`, `phpunit.xml`
(test suite **and** coverage source), and `packages.sh` (alphabetical position).

Two things stay manual on purpose:

- **`CLAUDE.md` part 1** — only the `# Package:` section is generated. Run
  `composer guidelines:sync` afterwards; baking a guidelines copy into the generator
  would recreate the drift the sync script exists to prevent.
- **The host-port table below** (`--services` only) — editing it marks all ~40
  `CLAUDE.md` copies as drifted at once, so the next `composer full` would fail for
  a brand-new module. The generator prints which ports to claim instead.

### 4 — Docker scaffold

Run from the new module root (requires `"ez-php/docker": "^1.0"` in `require-dev`):

```
vendor/bin/docker-init
```

This copies `Dockerfile`, `docker-compose.yml`, `.env.example`, `start.sh`, and `docker/` into the module, replacing `{{MODULE_NAME}}` placeholders. Existing files are never overwritten.

Pass `--services` to merge MySQL/Redis/Meilisearch service definitions directly into `docker-compose.yml` and uncomment the matching sections in `.env.example`, instead of adapting them by hand afterward:

```
vendor/bin/docker-init --services=mysql
vendor/bin/docker-init --services=redis
vendor/bin/docker-init --services=meilisearch
vendor/bin/docker-init --services=mysql,redis
```

After scaffolding:

1. Adapt `docker-compose.yml` — add or remove services (MySQL, Redis, Meilisearch) as needed
2. Adapt `.env.example` — fill in connection defaults matching the services above
3. Assign a unique host port for each exposed service (see table below)

**Allocated host ports:**

| Package | `DB_HOST_PORT` (MySQL) | `REDIS_PORT` | `MEILISEARCH_PORT` |
|---|---|---|---|
| root (`ez-php-project`) | 3306 | 6379 | 7700 |
| `ez-php/framework` | 3307 | — | — |
| `ez-php/orm` | 3309 | — | — |
| `ez-php/cache` | — | 6380 | — |
| `ez-php/queue` | 3310 | 6381 | — |
| `ez-php/rate-limiter` | — | 6382 | — |
| `ez-php/search` | — | — | 7701 |
| **next free** | **3311** | **6383** | **7702** |

Only set a port for services the module actually uses. Modules without external services need no port config.

> The `MEILISEARCH_PORT` column is the **host** port. Inside a Compose network the service is always reachable at `http://meilisearch:7700` regardless of the host mapping — only publish-side ports need to be unique.

### 5 — Monorepo scripts

`packages.sh` at the project root is the **central package registry**. Both `push_all.sh` and `update_all.sh` source it — the package list lives in exactly one place.

When adding a new module, add `"$ROOT/modules/<name>"` to the `PACKAGES` array in `packages.sh` in **alphabetical order** among the other `modules/*` entries (before `framework`, `ez-php`, and the root entry at the end).

---

# Package: ez-php/opcache

## Source structure

```
src/
├── PreloadConfig.php               — value object: output file path, scan paths, exclude patterns, require_once flag
├── PreloadException.php            — thrown when script generation fails (missing output dir, write error)
├── Preloader.php                   — scans directories, collects PHP files, writes the preload script
└── PreloaderServiceProvider.php    — binds PreloadConfig and Preloader into the container

tests/
├── TestCase.php                    — abstract base
├── PreloadConfigTest.php           — tests all constructor parameters and getters
└── PreloaderTest.php               — collect() and generate() with temp directories; covers excludes, subdirs, sorting, require_once, error cases
```

---

## Key classes and responsibilities

### PreloadConfig (`src/PreloadConfig.php`)

Immutable value object. Holds:

- `outputFile` — absolute path where the generated `preload.php` is written.
- `paths` — list of directories to scan recursively for `.php` files.
- `excludePatterns` — list of filename glob patterns (e.g. `*Test.php`) passed to `fnmatch()`. Files whose basename matches any pattern are omitted.
- `useRequireOnce` — when `true`, the generator uses `require_once` statements instead of `opcache_compile_file()`. Useful when class files have cross-file dependencies that must be resolved at load time.

---

### PreloadException (`src/PreloadException.php`)

Extends `RuntimeException`. Thrown by `Preloader::generate()` when:

- The output directory does not exist.
- `file_put_contents` returns `false`.

---

### Preloader (`src/Preloader.php`)

Two public methods:

- `collect(): string[]` — walks all configured paths using `RecursiveDirectoryIterator`, filters to `.php` files, applies exclude patterns via `fnmatch()`, deduplicates, and returns a sorted list of absolute paths.
- `generate(): int` — calls `collect()`, renders the preload script, writes it to `outputFile`, and returns the file count.

Non-existent or non-directory paths in `paths` are silently skipped — `realpath()` returning `false` is a safe no-op. This allows configuration to reference paths that may not be present in all environments (e.g. optional packages).

Generated script format (default — `opcache_compile_file`):

```php
<?php

// Auto-generated OPcache preload script.
// Generated by ez-php/opcache at 2026-03-29 12:00:00
// Total files: 42
// Usage: set opcache.preload=<this-file> in php.ini

if (function_exists('opcache_compile_file')) {
    opcache_compile_file('/var/www/html/src/Foo.php');
    // ...
}
```

Generated script format (`useRequireOnce: true`):

```php
<?php

// Auto-generated OPcache preload script.
// ...

require_once '/var/www/html/src/Foo.php';
// ...
```

---

### PreloaderServiceProvider (`src/PreloaderServiceProvider.php`)

`register()` binds `PreloadConfig` and `Preloader` lazily. Config values are read from `ConfigInterface` under the `opcache.*` namespace; if `ConfigInterface` is not bound, all values fall back to defaults (empty paths, no output file). Uses `try/catch` around the `ConfigInterface` resolution — the preloader is non-critical and must not crash the application on misconfiguration.

`boot()` is empty — this module has no HTTP endpoint and no console commands.

---

## Design decisions and constraints

- **Depends only on `ez-php/contracts`, not `ez-php/framework`.** The Preloader is a build-time or deploy-time tool. It does not need the Router, Database, or any runtime framework service. Limiting the dependency to contracts keeps it usable in any container-based context.
- **`opcache_compile_file` by default, `require_once` as opt-in.** `opcache_compile_file` compiles the file without executing it, which is safer for preloading. `require_once` is available for cases where class resolution requires execution order (e.g. constants or aliases defined at file scope).
- **Non-existent paths are silently skipped.** A missing directory in `paths` (e.g. an optional package that is not installed) is a no-op. This avoids hard failures in environments where not all packages are present.
- **No caching of the file list.** The `collect()` method scans the filesystem on every call. The preload script is generated once at deploy time (or via a Composer script), not on every request. There is no benefit to caching the list within the module.
- **No static facade.** Preloading is a one-shot generation step, not a runtime API called from application code. A facade would add complexity without value.
- **Output file is overwritten unconditionally.** `file_put_contents` replaces any existing file. The preload script is always regenerated from scratch to ensure it reflects the current state of the filesystem. No partial-update logic is needed.
- **Files are sorted lexicographically.** Deterministic ordering ensures that regenerating the preload script with the same input produces byte-identical output, making it easy to diff and track in version control.

---

## Testing approach

No external infrastructure required — all tests run in-process using temporary directories created via `sys_get_temp_dir()`. Each test creates its own uniquely named directory in `setUp()` and removes it in `tearDown()`.

- `PreloadConfigTest` — pure unit; verifies all constructor parameters and getters.
- `PreloaderTest` — filesystem-based; creates real `.php` files in a temp dir, exercises `collect()` and `generate()`, and asserts on the written script content.

Covered scenarios:
- Only `.php` files are collected (non-PHP files ignored)
- Exclude patterns are applied via `fnmatch()`
- Subdirectories are scanned recursively
- Non-existent paths are silently skipped
- Empty path list returns an empty array
- Output is sorted lexicographically
- Default mode writes `opcache_compile_file` wrapper
- `useRequireOnce: true` writes `require_once` statements
- Zero files: script is written with count 0
- Missing output directory throws `PreloadException`

---

## What does not belong in this module

- **Console command (`opcache:generate`)** — the `Preloader` is a plain PHP class; call it from a Composer script, a deploy step, or any console runner without needing a framework command.
- **Automatic preload on application boot** — preloading happens at the PHP engine level via `php.ini`, not during the request lifecycle.
- **OPcache status / statistics** — reading `opcache_get_status()` or `opcache_get_configuration()` belongs in a diagnostics tool or the `ez-php/health` module.
- **Invalidation or cache-clear logic** — `opcache_reset()` and `opcache_invalidate()` are runtime operations; they do not belong in a build-time generator.
- **Autoload optimisation** — `composer dump-autoload --optimize` is handled by Composer, not this module.
