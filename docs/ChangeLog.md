# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/).

## [Unreleased]

### Added

#### Phase 1 - Critical Bug Fixes
- Session retrieval from cache (primary) with database fallback for persistence across USSD requests
- Session state restoration: current menu, navigation history, form data, context
- Expired session recovery with configurable grace period
- Configurable model namespace via `database.model_namespace` config key
- Configurable session constants: `session.timeout`, `session.max_history`, `session.max_snapshots`

#### Phase 2 - Testing & Stability
- Comprehensive test suites for all menu types: Form, Wizard, Conditional, Paginated, SearchablePaginated
- Provider test coverage: Airtel, MTN, Generic (auto-detection, fallback)
- Service and infrastructure tests: Analytics, CacheManager, AuditLogger, DatabaseService
- Session persistence and recovery integration tests
- Builder tests for all 5 builder classes with fluent API validation
- 701 tests with 1128 assertions

#### Phase 3 - Events, Commands & Extensibility
- Laravel events: `SessionStarted`, `SessionResumed`, `SessionEnded`, `SessionExpired`, `MenuEntered`, `MenuExited`, `FormSubmitted`, `InputReceived`, `NavigationPerformed`
- Artisan commands: `ussd:cleanup-sessions`, `ussd:list-sessions`, `ussd:manage-access`, `ussd:health`
- `Ussd` facade with Laravel auto-discovery
- HTTP middleware: `UssdAuthentication` (gateway IP/signature verification), `UssdRateLimit` (per-phone rate limiting)

#### Phase 4 - Features & Hardening
- Internationalization (i18n) via `TranslationService` with per-session language selection
- `__ussd()` helper function for translated strings with parameter interpolation
- HMAC signature verification per provider (Safaricom/Africa's Talking, Airtel, MTN)
- Circuit breaker for external API calls (closed -> open -> half-open states) with configurable threshold and cooldown
- Session data encryption at rest via `SessionEncryptor` with configurable encrypted fields
- Analytics buffer flushing strategies: sync, async, and shutdown hooks
- Production deployment, performance tuning, and troubleshooting documentation
- Custom provider creation guide and database schema documentation

### Fixed
- `retrieveSession()` returning null -- sessions now persist correctly across multiple USSD requests
- Session migration method returning inverted boolean
- Transaction recovery handler signature mismatch (`($sessionData, $request, $config)` vs `($sessionData)`)
- Hardcoded `App\Models` namespace in `UssdDatabaseService` replaced with configurable namespace
- Hardcoded session timeout (300s), max interaction history (50), and max context snapshots (10) moved to config

## [v0.1.5] - 2026-01-27

### Added
- Tests for USSD sessions
- Support for data providers in tests

## [v0.1.4] - 2026-01-27

### Added
- Support for data providers in menu configuration
- Tests for data providers

## [v0.1.3] - 2026-01-27

### Added
- Support for PostgreSQL database driver

## [v0.1.2] - 2026-01-27

### Fixed
- Lint fixes

## [v0.1.1] - 2026-01-27

### Fixed
- Multiple issue fixes for USSD handling
- Lint fixes

## [v0.1.0] - 2026-01-26

### Added
- Initial release: USSD library scaffold
- Migration for USSD sessions
- Git workflow for CI/CD
- PHPUnit configuration
- Test setup with scaffolded test suites
- Upgraded to PHP 8.5

### Changed
- Cleaned up service provider
- PHPStan fixes

### Fixed
- Test fixes across multiple iterations
- Lint fixes
