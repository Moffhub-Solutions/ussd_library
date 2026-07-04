# USSD Package — Implementation Plan

> **Priority Order:** Third package to implement. The largest and most complex package (~11K LOC). Has critical bugs that must be fixed before production use.
>
> **Status:** ✅ All phases completed. 701 tests, 1128 assertions.

---

## Phase 1 — Critical Bug Fixes (Week 7–8) ✅ COMPLETED

### 1.1 Fix Session Persistence (retrieveSession returns null) ✅
- **File:** `src/UssdFramework.php` (line ~478)
- **Issue:** `retrieveSession()` always returns `null`. Sessions do not survive beyond a single USSD request. This is a **showstopper** for production.
- **Completed:**
  - [x] Implement session retrieval from cache (primary) and database (fallback)
  - [x] Use `session_id` from the provider request to look up existing session
  - [x] Restore session state: current menu, navigation history, form data, context
  - [x] Handle expired sessions with grace period recovery (config already exists)
  - [x] Add integration test: send request -> get session -> send follow-up -> verify continuity
  - [x] Add test for session expiration and recovery
  - [x] Add test for cache miss with database fallback

### 1.2 Fix Session Migration Inverted Logic ✅
- **File:** `src/UssdFramework.php` (line ~1007)
- **Issue:** Session migration method returns inverted boolean — succeeds when it should fail and vice versa.
- **Completed:**
  - [x] Fix the return value logic
  - [x] Add unit test verifying correct return on success and failure
  - [x] Test migration from old session format to new format

### 1.3 Fix Transaction Recovery Handler Signature ✅
- **File:** `src/UssdFramework.php` (line ~527)
- **Issue:** Recovery handlers receive `($sessionData, $request, $config)` but implementations expect only `($sessionData)`.
- **Completed:**
  - [x] Align the handler signature with the call site
  - [x] Update all recovery handler implementations
  - [x] Add test for recovery handler invocation with correct arguments

### 1.4 Fix Hardcoded App\Models Namespace ✅
- **File:** `src/Services/UssdDatabaseService.php` (line 7)
- **Issue:** Imports from `App\Models` — breaks when consumed as a package.
- **Completed:**
  - [x] Replace with configurable model namespace from `ussd.php` config
  - [x] Add `database.model_namespace` config key
  - [x] Default to the package's own models

### 1.5 Extract Hardcoded Constants ✅
- **Files:** `src/UssdSession.php`
- **Issue:** Session timeout (300s), max interaction history (50), max context snapshots (10) are hardcoded.
- **Completed:**
  - [x] Move to config: `session.timeout`, `session.max_history`, `session.max_snapshots`
  - [x] Read from config with current values as defaults
  - [x] Add tests verifying config overrides are respected

---

## Phase 2 — Testing & Stability (Week 9–12) ✅ COMPLETED

### 2.1 Menu Type Tests ✅
- **Files:** `tests/Unit/Menus/`
- **Target:** Every menu type has comprehensive tests.
- **Completed:**
  - [x] `FormMenuTest.php`
    - Multi-step form collection
    - Field validation (required, minLength, phone, email, custom)
    - Invalid input re-prompting
    - Form data persistence across steps
    - Back navigation mid-form
  - [x] `WizardMenuTest.php`
    - Step transitions (forward, backward, skip)
    - Step validation and completion
    - Wizard state persistence
    - Conditional step logic
  - [x] `ConditionalMenuTest.php`
    - Condition evaluation (true/false branches)
    - Multiple conditions
    - Default fallback menu
    - Condition with session data
  - [x] `PaginatedMenuTest.php`
    - Page navigation (next, previous)
    - Boundary pages (first, last)
    - Empty data set
    - Single page data set
  - [x] `SearchablePaginatedMenuTest.php`
    - Search query handling
    - Search results pagination
    - No results scenario
    - Search reset

### 2.2 Provider Tests ✅
- **Files:** `tests/Unit/Providers/`
- **Completed:**
  - [x] `AirtelProviderTest.php` — request parsing, field mapping, response formatting
  - [x] `MtnProviderTest.php` — request parsing, field mapping, response formatting
  - [x] `GenericProviderTest.php` — auto-detection logic, fallback behavior
  - [x] Test provider factory with all registered providers
  - [x] Test custom provider registration

### 2.3 Service & Infrastructure Tests ✅
- **Files:** `tests/Unit/`
- **Completed:**
  - [x] `UssdAnalyticsTest.php`
    - Event tracking (menu, session, performance)
    - Buffer flushing (manual and automatic)
    - Phone number hashing
    - Metric aggregation
  - [x] `UssdCacheManagerTest.php`
    - Menu caching (store, retrieve, invalidate)
    - Data provider result caching
    - TTL configuration
    - Cache key generation
  - [x] `UssdAuditLoggerTest.php`
    - Security event logging
    - Log levels (info, warning, error, critical)
    - Log context data
  - [x] `UssdDatabaseServiceTest.php`
    - CRUD operations
    - Query scopes
    - Configurable model namespace

### 2.4 Session Persistence & Recovery Tests ✅
- **File:** `tests/Feature/SessionPersistenceTest.php`
- **Completed:**
  - [x] Test full session lifecycle: create -> persist -> retrieve -> update -> expire
  - [x] Test cache + database hybrid persistence
  - [x] Test session recovery within grace period
  - [x] Test session recovery after grace period (should fail)
  - [x] Test context preservation on expiration
  - [x] Test session snapshot creation and restoration
  - [x] Test menu history navigation (back, home)
  - [x] Test concurrent session access

### 2.5 Builder Tests ✅
- **Files:** `tests/Unit/Builders/`
- **Completed:**
  - [x] Test all 5 builder classes
  - [x] Test fluent API chaining
  - [x] Test builder validation (missing required fields)
  - [x] Test builder output matches expected menu configuration
  - [x] Total: 701 tests reached

---

## Phase 3 — Events, Commands & Extensibility (Week 13–14) ✅ COMPLETED

### 3.1 Laravel Event Dispatching ✅
- **Files:** `src/Events/`, `src/UssdFramework.php`, `src/UssdSession.php`
- **Completed:**
  - [x] Create events:
    - `SessionStarted` (session_id, phone, provider)
    - `SessionResumed` (session_id, phone, was_recovered)
    - `SessionEnded` (session_id, phone, duration, menus_visited)
    - `SessionExpired` (session_id, phone, last_menu)
    - `MenuEntered` (session_id, menu_name, from_menu)
    - `MenuExited` (session_id, menu_name, to_menu, selection)
    - `FormSubmitted` (session_id, menu_name, form_data)
    - `InputReceived` (session_id, menu_name, raw_input)
    - `NavigationPerformed` (session_id, action: back|home|search)
  - [x] Dispatch from appropriate locations in framework/session
  - [x] Document all events with payload details in README
  - [x] Add tests verifying each event is dispatched at the right time

### 3.2 Artisan Commands ✅
- **Files:** `src/Console/Commands/`
- **Completed:**
  - [x] `ussd:cleanup-sessions` — Delete expired sessions beyond retention period
    - Accept `--older-than` flag (default: 24 hours)
    - Accept `--dry-run` flag to preview count
    - Log count of deleted sessions
  - [x] `ussd:list-sessions` — Show active sessions
    - Table output: session_id, phone (masked), current_menu, started_at, last_activity
    - Accept `--provider` filter
  - [x] `ussd:manage-access` — Manage whitelist/blacklist
    - Subcommands: `add`, `remove`, `list`
    - Accept `--type` (whitelist|blacklist)
    - Accept `--phone` or `--pattern`
  - [x] `ussd:health` — Check framework health
    - Verify cache connectivity
    - Verify database connectivity
    - Report active session count
    - Report error rate (last hour)
  - [x] Register all commands in `UssdServiceProvider`
  - [x] Add tests for each command

### 3.3 Register Facade ✅
- **Files:** `src/Facades/Ussd.php`, `src/UssdServiceProvider.php`
- **Completed:**
  - [x] Create `Ussd` facade pointing to `UssdFramework`
  - [x] Register in service provider
  - [x] Add Laravel auto-discovery in `composer.json`
  - [x] Document facade usage in README

### 3.4 Middleware Support ✅
- **Files:** `src/Http/Middleware/`
- **Completed:**
  - [x] `UssdAuthentication` — Verify request comes from known USSD gateway (IP/signature)
  - [x] `UssdRateLimit` — Apply rate limiting per phone number (wraps existing `UssdRateLimiter`)
  - [x] Register middleware aliases in service provider
  - [x] Document middleware setup in README

---

## Phase 4 — Features & Hardening (Week 15–18) ✅ COMPLETED

### 4.1 Internationalization (i18n) ✅
- **Files:** `src/Services/TranslationService.php`, `resources/lang/`
- **Completed:**
  - [x] Create `TranslationService` wrapping Laravel's translator
  - [x] Support per-session language selection (store in session context)
  - [x] Add `__ussd('key', $params, $locale)` helper function
  - [x] Translate built-in strings: navigation prompts, error messages, validation messages
  - [x] Support language files in `resources/lang/{locale}/ussd.php`
  - [x] Allow apps to publish and override language files
  - [x] Add config key: `localization.default_locale`, `localization.supported_locales`
  - [x] Add tests for language switching mid-session

### 4.2 HMAC Signature Verification for Providers ✅
- **Files:** `src/Providers/`, `src/Security/RequestVerifier.php`
- **Completed:**
  - [x] Implement HMAC verification per provider:
    - Safaricom/Africa's Talking: API key signature
    - Airtel: Callback token validation
    - MTN: Certificate-based verification
  - [x] Add `providers.{name}.secret` config key
  - [x] Reject unverified requests with 403
  - [x] Add tests for valid/invalid signatures

### 4.3 Circuit Breaker for External API Calls ✅
- **Files:** `src/Services/CircuitBreaker.php`, `src/DataProviders/ApiDataProvider.php`
- **Completed:**
  - [x] Implement circuit breaker (closed -> open -> half-open states)
  - [x] Track failure count per endpoint
  - [x] Open circuit after N consecutive failures (configurable, default: 5)
  - [x] Return cached/fallback data when circuit is open
  - [x] Half-open: allow one test request after cooldown period
  - [x] Add config: `circuit_breaker.threshold`, `circuit_breaker.cooldown_seconds`
  - [x] Log circuit state transitions
  - [x] Add tests for all state transitions

### 4.4 Session Data Encryption ✅
- **Files:** `src/UssdSession.php`, `src/Security/SessionEncryptor.php`
- **Completed:**
  - [x] Add optional encryption for session data at rest
  - [x] Use Laravel's `Crypt` facade with app key
  - [x] Encrypt: form data, context variables (configurable which fields)
  - [x] Add config: `security.encrypt_session_data` (default: false)
  - [x] Add config: `security.encrypted_fields` (array of field names to encrypt)
  - [x] Add tests for encrypt/decrypt round-trip
  - [x] Ensure encrypted data works with session recovery

### 4.5 Guaranteed Analytics Buffer Flushing ✅
- **File:** `src/Analytics/UssdAnalytics.php`
- **Completed:**
  - [x] Register a `shutdown` function to flush remaining buffer
  - [x] Add `register_shutdown_function` in analytics constructor
  - [x] Alternatively: dispatch a queued job to persist analytics asynchronously
  - [x] Add config: `analytics.flush_strategy` (sync|async|shutdown)
  - [x] Add tests for buffer flushing under various termination scenarios

### 4.6 Production Documentation ✅
- **File:** `README.md`, `docs/`
- **Completed:**
  - [x] Add production deployment checklist:
    - Cache driver selection (Redis recommended)
    - Database migration verification
    - Provider credential setup
    - Rate limit tuning
    - Session timeout configuration
    - Analytics retention policy
  - [x] Add performance tuning guide:
    - Cache TTL optimization
    - Database query optimization
    - Session cleanup scheduling
    - Analytics buffer size tuning
  - [x] Add troubleshooting guide:
    - Session not persisting
    - Provider not detected
    - Rate limiting too aggressive
    - Analytics data missing
  - [x] Add custom provider creation guide (step-by-step)
  - [x] Add database schema documentation
  - [x] Document all events from Phase 3
  - [x] Document all Artisan commands from Phase 3

---

## Implementation Checklist Summary

| Phase | Items | Est. Effort | Status |
|-------|-------|-------------|--------|
| Phase 1 — Critical Bug Fixes | 5 work items | 2 weeks | **✅ COMPLETED** |
| Phase 2 — Testing & Stability | 5 work items | 4 weeks | **✅ COMPLETED** |
| Phase 3 — Events, Commands & Extensibility | 4 work items | 2 weeks | **✅ COMPLETED** |
| Phase 4 — Features & Hardening | 6 work items | 4 weeks | **✅ COMPLETED** |

---

## Dependencies & Prerequisites

- Redis or Memcached for session caching (array driver for tests)
- MySQL/PostgreSQL for database persistence
- At least one USSD provider sandbox account for integration testing
- Queue worker for async analytics flushing
- PHP 8.5+ with strict types

## Success Criteria

- [x] Sessions persist correctly across multiple USSD requests (Phase 1 — blocker)
- [x] All critical bugs fixed and verified with tests
- [x] Test coverage >= 80% across all menu types, providers, and services
- [x] Events dispatched for all lifecycle transitions
- [x] Artisan commands available for operational management
- [x] Provider payloads verified via HMAC signatures
- [x] External API calls protected by circuit breaker
- [x] README covers production deployment end-to-end

---

## Risk Register

| Risk | Impact | Mitigation |
|------|--------|------------|
| Session persistence fix breaks existing behavior | High | Feature-flag new persistence, keep legacy path as fallback during migration |
| Provider signature verification blocks legitimate requests | High | Add `verify_signatures` config toggle (default: false in dev, true in prod) |
| Circuit breaker opens too aggressively | Medium | Tune threshold per-provider, start conservative (10 failures) |
| i18n increases response payload size | Low | Lazy-load translations, cache per locale |
| Encryption adds latency to session reads | Medium | Benchmark, only encrypt specified fields, use hardware-accelerated AES |

## Final Results

- **Total tests:** 701 tests, 1128 assertions
