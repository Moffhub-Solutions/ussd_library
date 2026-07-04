# USSD Library Roadmap

Improvement backlog, drawn from real integration friction. Each item is written
as a self-contained ticket: problem, evidence (anchored to `file:line`),
proposed change, and payoff. Grouped by impact area. Status reflects work already
landed in this branch.

Legend: [done] shipped, [wip] in progress, [todo] not started.

---

## Correctness / config

### C1. Sane production defaults [done]
**Problem:** Defaults assumed a request-per-transaction API, not an interactive
menu. Two bit us: `10` requests/min and "empty input = suspicious" (the initial
dial always carries empty input).
**Evidence:**
- `src/Security/UssdRateLimiter.php` constructor (was `max_requests_per_minute => 10`).
- `src/Security/UssdInputSanitizer.php` `validateMenuOption()` regex used `+` (>=1 char), so `""` failed.
**Change:** Empty input early-returns valid; rate-limit fallback raised to
`60/600/3000` (min/hour/day); `strict_mode` already defaults `false`.
**Payoff:** The framework works out of the box for a normal menu.

### C2. Config is actually read [done]
**Problem:** The published `config/ussd.php` was dead. `mergeDefaultConfig()`
never called `config('ussd')`, and the rate limiter hardcoded its limits.
**Evidence:**
- `src/UssdFramework.php` `mergeDefaultConfig()` was `array_merge([defaults], $config)`.
- `src/Security/UssdRateLimiter.php` built with no args, ignoring config.
**Change:** `mergeDefaultConfig()` now `array_replace_recursive(defaults, config('ussd'), $config)`
(deep, so nested `security.*`/`database.*` blocks merge instead of clobbering
siblings). Rate limiter layers `config('ussd.rate_limiting')`. Builder's
`configureSession()` uses `array_replace_recursive` too, so partial
`configure*()` calls no longer wipe sibling keys.
**Note:** The original patch pointed at `config('ussd.security.rate_limits')`,
which does not exist. The real keys are `rate_limiting.*` (limits) and
`security.rate_limiting` (on/off bool). Corrected.

### C3. One session table, clearly named [done]
**Problem:** `ussd_sessions` vs `ussd_user_sessions` is confusing; one is empty
for normal use.
**Change:** Documented in the README ("Session tables"): `ussd_user_sessions` is
the runtime store; `ussd_sessions` is access-management/admin-only. Consolidation
of the schema is left as a larger follow-up.
**Payoff:** Removes the "which table do I query?" tax.

### C4. Published, authoritative config + reference [done]
**Change:** Added the `ussd-config` publish tag
(`vendor:publish --tag=ussd-config`) alongside the generic `config` tag, and a
README config-reference table (key, env var, default) plus a note that the
published file is the authoritative, deep-merged control surface.

---

## Extensibility (swap, don't fork)

### E1. Interfaces + container binding for pluggable parts [done]
**Problem:** The framework `new`d concrete classes, so the only escape hatch was
disabling a component.
**Change:** Added `RateLimiterInterface`, `InputSanitizerInterface`,
`AuditLoggerInterface` (`src/Interfaces/`); the concretes implement them.
`initializeComponents()` now calls `resolveComponent()`: it uses an app-provided
container binding if one exists, else falls back to the config-aware default.
Deliberately *not* default-bound in the provider, so binding stays an explicit
override and the framework's per-instance config (Patch 2) is preserved.
`instanceof` gates widened to the interfaces. `setRateLimiter/setInputSanitizer/
setAuditLogger` added.
**`SessionStoreInterface` [done]:** `UssdSession`'s cache reads/writes now go
through `SessionStoreInterface`, defaulting to `CacheSessionStore` (behaviour
identical). Bind your own in the container to change where live session state
lives. Non-breaking; the constructor signature is unchanged. Covered by
`tests/Feature/SessionStoreTest.php`.
**Payoff:** Apps customize behavior without touching or forking the package.
Covered by `tests/Feature/SwappableComponentsTest.php`.

### E2. Builder setters for implementations [done]
**Change:** `->rateLimiter($impl)`, `->inputSanitizer($impl)`, `->provider($impl)`
on `UssdBuilder`, proxying to the framework setters.

---

## Debuggability (highest pain)

### D1. Debug / rethrow mode [done]
**Problem:** `process()` caught menu exceptions and returned "Service
temporarily unavailable", so the real cause only survived in logs. Recovering it
in tests meant `Log::listen`.
**Evidence:** `src/UssdFramework.php` catch block (~line 444).
**Change:** Added `debug` config (`USSD_DEBUG`, default false). When true,
`process()` rethrows after logging. The `on_error` hook already fires either way
(`handleError()`, ~line 1108) via `addHook('on_error', ...)`.

### D2. Test harness / fakes [done]
**Problem:** Testing a menu meant wiring a real aggregator or disabling many
config flags.
**Change:** `Moffhub\Ussd\Testing\UssdTester` (`src/Testing/UssdTester.php`).
`UssdTester::fake()` builds a framework with test defaults (rate limiting,
analytics, audit and DB off; `debug` on). Fluent `dial()`/`send()`/`drive([...])`
plus `assertSee`/`assertContinue`/`assertEnded`/`assertType`. Debug mode now also
rethrows from the inner menu-processing catch (`UssdFramework.php` ~line 855), so
menu-handler exceptions surface in tests instead of the generic CON error.
Covered by `tests/Feature/UssdTesterTest.php`.
**Payoff:** Unblocks everyone building menus.

### D3. `php artisan ussd:simulate {phone}` [todo]
**Change:** Interactive terminal REPL that walks the menu locally.
**Payoff:** Build/QA without a phone or a gateway.

---

## Observability

### O1. Menu statistics wired to session lifecycle [wip]
**Problem:** `ussd_menu_statistics` (access/completion/drop-off) was never
populated; `updateMenuStatistics()` had no caller, and the report methods
(`identifyDropOffPoints`, etc.) were stubs returning `[]`.
**Evidence:** `src/Services/UssdDatabaseService.php` `updateMenuStatistics()` (~line 360);
`src/Analytics/UssdAnalytics.php` stubs (~lines 819-842).
**Change (done):** `process()` now rolls each interaction into the aggregate
(`access_count` every request, `completion_count` on terminal `END`) at
`src/UssdFramework.php` (~line 397).
**Change (done):** Drop-off is now recorded by the sweeper (O2), which sets
`drop_off_count` for the last menu of abandoned sessions.
**Change (todo):** Fill in the stubbed analytics report methods
(`identifyDropOffPoints`, `calculateCompletionRate`, ...) to read from the
now-populated `ussd_menu_statistics` table.

### O2. Session sweeper + SessionExpired [done]
**Problem:** `SessionExpired` only fired lazily on a later request, so
abandoners were never counted.
**Change:** `SweepSessionsCommand` (`php artisan ussd:sweep-sessions`) finalizes
sessions inactive past the timeout that never completed: emits `SessionExpired`,
records drop-off via `updateMenuStatistics(..., droppedOff: true)`, calls
`MetricsRecorderInterface::sessionAbandoned()`, and marks `ended_at` so each is
finalized once. `--timeout` / `--dry-run`. Covered in `tests/Unit/Console/CommandsTest.php`.
**Payoff:** Funnel/abandonment measurable without app-side reinvention.

### O3. Optional metrics hook [done]
**Change:** `MetricsRecorderInterface` (menuEntered / menuCompleted /
sessionAbandoned / dwell), keyed by menu only (cardinality). Resolved from the
container with a `NullMetricsRecorder` default; the framework calls it inline and
the sweeper reports abandonment. Covered by `tests/Feature/MetricsRecorderTest.php`.

---

## Robustness

### R1. Duplicate-request dedupe [done]
**Problem:** Aggregators retry. Without dedupe a retried step is processed twice,
which matters once menus have side effects (STK push).
**Change:** `UssdFramework::handle()` keys on `sha1(phone|session|input)` and, if
the same request arrives within `deduplication.window` seconds (default 5),
replays the cached `UssdResponse` instead of reprocessing. Config
`deduplication.enabled` / `.window`. Covered by
`tests/Feature/RequestDeduplicationTest.php`.

### R2. Typed config object [done]
**Change:** Additive `Moffhub\Ussd\Support\UssdConfig` (`final readonly`) wraps
the config array with dot-notation `get()`, typed getters, convenience accessors,
and a recursive `merge()` that preserves sibling keys. Exposed via
`UssdFramework::config()`; `getConfig(): array` and all existing array access are
unchanged, so it is non-breaking (full replacement of the 174 array sites is left
as optional future work). Covered by `tests/Unit/Support/UssdConfigTest.php`.

### R4. Harden early-error cleanup [done]
**Problem:** Found while adding E1: if an exception is thrown before the session
is initialized (e.g. the default rate limiter hitting an unmigrated
`ussd_access_lists` table because `use_database_lists` defaults true), the
`finally` in `handle()` called `performCleanup()` / `handleError()` which access
`$this->session`, raising a secondary "typed property must not be accessed before
initialization" that masked the original error.
**Change:** `handleError`, `performCleanup` and `trackPerformance` now guard on
`isset($this->session)`. The rate limiter's `isWhitelisted`/`isBlacklisted` catch
store failures and fail open (with a logged warning), so a missing access-list
table degrades instead of crashing the request path. Covered by
`tests/Feature/EarlyErrorResilienceTest.php`.

### R3. Type-safe, validated menu names [done]
**Problem:** Navigation targets were unchecked strings; a typo or rename failed
blank at runtime (the generic "Service error"), not at build.
**Change:** `NavigateAction` and `UssdHelpers::navigateAction()` now accept
`string|MenuNameInterface|BackedEnum` (the framework already resolved enums in
`getMenu`/`hasMenu`/`navigateToMenu`). New `DeclaresNavigationTargets` interface,
implemented by `SimpleMenu`, exposes a menu's `NavigateAction` targets.
`UssdFramework::validateMenuReferences()` asserts each declared target resolves
to a registered menu and throws
`menu 'x' referenced by 'y' but not registered`. `UssdBuilder::build()` runs it
(config `validate_menu_references`, default true). Targets inside opaque closures
are not inspectable and are not checked. Covered by
`tests/Feature/MenuReferenceValidationTest.php`.
**Follow-up:** other menu types (paginated, wizard, conditional) can adopt
`DeclaresNavigationTargets` to widen coverage.

---

## Suggested order (effort / payoff)

1. **D1 debug/rethrow** [done] + **D2 test harness** - unblocks menu builders.
2. **C1/C2 config-actually-works** [done] + **E1/E2 interfaces & setters** - swap, don't fork.
3. **O2 sweeper** + **O3 metrics** - measurable funnel.
4. **R3 menu-name validation**, **R1 dedupe**, **C3/C4 docs**, **R2 typed config** - polish and hardening.
