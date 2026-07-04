# USSD Framework - Production Deployment Guide

## Table of Contents

- [Deployment Checklist](#deployment-checklist)
- [Cache Driver Selection](#cache-driver-selection)
- [Database Setup](#database-setup)
- [Provider Configuration](#provider-configuration)
- [Security Configuration](#security-configuration)
- [Performance Tuning](#performance-tuning)
- [Monitoring & Analytics](#monitoring--analytics)
- [Troubleshooting](#troubleshooting)
- [Events Reference](#events-reference)
- [Artisan Commands Reference](#artisan-commands-reference)
- [Middleware Reference](#middleware-reference)

---

## Deployment Checklist

Before going live, verify each item:

- [ ] **Cache driver**: Switch from `array` to `redis` or `memcached`
- [ ] **Database migrations**: Run `php artisan migrate` to create USSD tables
- [ ] **Provider credentials**: Set provider API keys/tokens in `.env`
- [ ] **Signature verification**: Enable `USSD_VERIFY_SIGNATURES=true` in production
- [ ] **Rate limiting**: Tune limits for expected traffic volume
- [ ] **Session timeout**: Set `USSD_SESSION_TIMEOUT` appropriate for your use case (default: 300s)
- [ ] **Session encryption**: Enable `USSD_ENCRYPT_SESSION_DATA=true` if handling sensitive data
- [ ] **Analytics retention**: Configure `analytics.retention_days` for compliance
- [ ] **Session cleanup**: Schedule `ussd:cleanup-sessions` in your cron
- [ ] **Health check**: Verify `php artisan ussd:health` passes
- [ ] **Queue worker**: Start a queue worker if using async analytics flushing
- [ ] **Gateway authentication**: Configure `gateway_authentication.allowed_ips`

---

## Cache Driver Selection

Redis is strongly recommended for production:

```env
CACHE_DRIVER=redis
USSD_CACHE_ENABLED=true
USSD_CACHE_MENU_TTL=3600
USSD_CACHE_DATA_PROVIDER_TTL=600
```

Why Redis over other drivers:
- Atomic operations prevent session corruption under concurrent access
- TTL-based expiration matches USSD session lifecycle
- Pub/Sub can be used for real-time session monitoring
- Cluster support for horizontal scaling

---

## Database Setup

Run migrations:

```bash
php artisan migrate
```

Or publish and customize:

```bash
php artisan vendor:publish --provider="Moffhub\Ussd\UssdServiceProvider" --tag=migrations
```

### Tables Created

| Table | Purpose |
|-------|---------|
| `ussd_user_sessions` | Active and historical sessions |
| `ussd_analytics` | Event tracking and metrics |
| `ussd_audit_logs` | Security and action audit trail |
| `ussd_security_events` | Security incident records |
| `ussd_access_lists` | Whitelist/blacklist entries |
| `ussd_rate_limits` | Per-phone rate limit tracking |
| `ussd_performance_metrics` | Response time and memory tracking |
| `ussd_business_metrics` | Custom business KPIs |
| `ussd_session_recovery_logs` | Session recovery attempts |
| `ussd_menu_statistics` | Menu usage analytics |

---

## Provider Configuration

### Safaricom / Africa's Talking

```env
USSD_PROVIDER_DEFAULT=safaricom
USSD_SAFARICOM_SECRET=your-api-key-here
```

### Airtel

```env
USSD_PROVIDER_DEFAULT=airtel
USSD_AIRTEL_SECRET=your-callback-token-here
USSD_COUNTRY_CODE=254
```

### MTN

```env
USSD_PROVIDER_DEFAULT=mtn
USSD_MTN_SECRET=your-hmac-secret-here
USSD_COUNTRY_CODE=234
```

### Auto-Detection

```env
USSD_PROVIDER_AUTO_DETECT=true
USSD_PROVIDER_DEFAULT=generic
```

---

## Security Configuration

### Signature Verification

Enable to reject spoofed requests:

```env
USSD_VERIFY_SIGNATURES=true
```

Each provider uses a different verification method:
- **Safaricom**: API key in `X-API-Key` header
- **Airtel**: Callback token in `Authorization` or `X-Callback-Token` header
- **MTN**: HMAC-SHA256 signature of request body in `X-Signature` header

### Session Data Encryption

Encrypt sensitive fields stored in cache/database:

```env
USSD_ENCRYPT_SESSION_DATA=true
```

Configure which fields to encrypt in `config/ussd.php`:

```php
'security' => [
    'encrypt_session_data' => true,
    'encrypted_fields' => [
        'form_data.pin',
        'form_data.account_number',
        'user_data.secret_token',
    ],
],
```

### Gateway Authentication

Restrict requests to known gateway IPs:

```env
USSD_GATEWAY_AUTH_ENABLED=true
USSD_GATEWAY_ALLOWED_IPS=10.0.0.1,10.0.0.2
```

### Rate Limiting

```env
USSD_RATE_LIMIT_PER_MINUTE=10
USSD_RATE_LIMIT_PER_HOUR=100
USSD_RATE_LIMIT_PER_DAY=500
```

---

## Performance Tuning

### Cache TTL Optimization

- **Menu content TTL** (`USSD_CACHE_MENU_TTL`): Set higher (3600s+) for static menus, lower (60s) for dynamic content
- **Data provider TTL** (`USSD_CACHE_DATA_PROVIDER_TTL`): Balance freshness vs. API load

### Database Query Optimization

- Index `ussd_user_sessions` on `phone_number` and `session_id`
- Index `ussd_analytics` on `timestamp` and `event_type`
- Use database connection pooling in production

### Session Cleanup Scheduling

Add to `app/Console/Kernel.php` (or `routes/console.php`):

```php
Schedule::command('ussd:cleanup-sessions --older-than=24h')->daily();
```

### Analytics Buffer Tuning

```env
# Buffer size before auto-flush (higher = fewer DB writes)
USSD_ANALYTICS_BUFFER_SIZE=100

# Flush strategy: sync (immediate), async (queued), shutdown (on process exit)
USSD_ANALYTICS_FLUSH_STRATEGY=async
```

For high-traffic deployments, use `async` with a dedicated queue:

```env
USSD_ANALYTICS_FLUSH_STRATEGY=async
QUEUE_CONNECTION=redis
```

### Circuit Breaker for External APIs

Protect against cascading failures from external API calls:

```env
# Open circuit after N consecutive failures
USSD_CIRCUIT_BREAKER_THRESHOLD=5

# Seconds before allowing a test request
USSD_CIRCUIT_BREAKER_COOLDOWN=60
```

---

## Monitoring & Analytics

### Health Check

```bash
php artisan ussd:health
```

Checks cache connectivity, database connectivity, active session count, and recent error rate.

### Real-Time Metrics

Access via the analytics service:

```php
$analytics = app(\Moffhub\Ussd\Analytics\UssdAnalytics::class);
$metrics = $analytics->getRealTimeMetrics();
```

Returns: `active_sessions`, `total_interactions`, `error_count`, `popular_menus`, `performance_stats`.

---

## Troubleshooting

### Session Not Persisting

1. Verify cache driver is not `array` in production
2. Check `USSD_SESSION_TIMEOUT` is sufficient (default: 300s)
3. Run `php artisan ussd:health` to verify cache connectivity
4. Check logs for "Failed to save session" errors
5. Verify `USSD_PERSISTENCE_STRATEGY` is set to `hybrid` or `cache`

### Provider Not Detected

1. Enable auto-detection: `USSD_PROVIDER_AUTO_DETECT=true`
2. Check request payload matches expected format for the provider
3. Set explicit provider: `USSD_PROVIDER_DEFAULT=safaricom`
4. Review provider field mapping in provider adapter class

### Rate Limiting Too Aggressive

1. Increase limits: `USSD_RATE_LIMIT_PER_MINUTE=20`
2. Check if phone number is on whitelist: `php artisan ussd:manage-access list --type=whitelist`
3. Add VIP numbers: `php artisan ussd:manage-access add --type=whitelist --phone=+254712345678`
4. Verify rate limit cache is clearing properly

### Analytics Data Missing

1. Check `USSD_ANALYTICS_ENABLED=true`
2. Verify database tables exist (run migrations)
3. For async flushing, ensure queue worker is running
4. Check buffer size: very large buffers may not flush on short-lived requests (use `shutdown` strategy)
5. Review logs for "Failed to flush analytics buffer" errors

### Signature Verification Rejecting Valid Requests

1. Verify provider secret matches in `.env`
2. Check request headers are being forwarded by load balancers/proxies
3. Temporarily disable: `USSD_VERIFY_SIGNATURES=false` to confirm it is the signature check
4. Check logs for "RequestVerifier: Missing" warnings

### Circuit Breaker Staying Open

1. Check logs for "CircuitBreaker: Circuit opened" messages
2. Verify external API is responsive
3. Increase threshold: `USSD_CIRCUIT_BREAKER_THRESHOLD=10`
4. Reduce cooldown: `USSD_CIRCUIT_BREAKER_COOLDOWN=30`

---

## Events Reference

All events use `Illuminate\Foundation\Events\Dispatchable` and can be listened to with standard Laravel event listeners.

| Event | Payload | When Dispatched |
|-------|---------|-----------------|
| `SessionStarted` | `sessionId`, `phone`, `provider` | New USSD session begins |
| `SessionResumed` | `sessionId`, `phone`, `wasRecovered` | Existing session resumed |
| `SessionEnded` | `sessionId`, `phone`, `duration`, `menusVisited` | Session ends normally |
| `SessionExpired` | `sessionId`, `phone`, `lastMenu` | Session times out |
| `MenuEntered` | `sessionId`, `menuName`, `fromMenu` | User enters a menu |
| `MenuExited` | `sessionId`, `menuName`, `toMenu`, `selection` | User leaves a menu |
| `FormSubmitted` | `sessionId`, `menuName`, `formData` | Form data submission complete |
| `InputReceived` | `sessionId`, `menuName`, `rawInput` | User sends input |
| `NavigationPerformed` | `sessionId`, `action` | User navigates (back/home/search) |

### Listening to Events

```php
// In EventServiceProvider or event discovery
use Moffhub\Ussd\Events\SessionStarted;
use Moffhub\Ussd\Events\FormSubmitted;

Event::listen(SessionStarted::class, function (SessionStarted $event) {
    Log::info("USSD session started: {$event->sessionId} from {$event->phone}");
});

Event::listen(FormSubmitted::class, function (FormSubmitted $event) {
    // Process form data, trigger workflows, etc.
    ProcessRegistration::dispatch($event->formData);
});
```

---

## Artisan Commands Reference

### `ussd:cleanup-sessions`

Delete expired sessions beyond the retention period.

```bash
# Delete sessions older than 24 hours (default)
php artisan ussd:cleanup-sessions

# Delete sessions older than 7 days
php artisan ussd:cleanup-sessions --older-than=7d

# Preview without deleting
php artisan ussd:cleanup-sessions --dry-run
```

### `ussd:list-sessions`

Show active USSD sessions.

```bash
# List all active sessions
php artisan ussd:list-sessions

# Filter by provider
php artisan ussd:list-sessions --provider=safaricom
```

Output: session_id, phone (masked), current_menu, started_at, last_activity.

### `ussd:manage-access`

Manage whitelist and blacklist entries.

```bash
# Add to whitelist
php artisan ussd:manage-access add --type=whitelist --phone=+254712345678 --reason="VIP"

# Remove from blacklist
php artisan ussd:manage-access remove --type=blacklist --phone=+254999999999

# List all whitelist entries
php artisan ussd:manage-access list --type=whitelist

# List all blacklist entries
php artisan ussd:manage-access list --type=blacklist
```

### `ussd:health`

Check framework health status.

```bash
php artisan ussd:health
```

Checks:
- Cache driver connectivity
- Database connectivity
- Active session count
- Error rate (last hour)

---

## Middleware Reference

### `ussd.auth` - Gateway Authentication

Verifies requests come from known USSD gateways by IP address and optional signature.

```php
Route::post('/ussd', [UssdController::class, 'handle'])
    ->middleware('ussd.auth');
```

Configure in `.env`:

```env
USSD_GATEWAY_AUTH_ENABLED=true
USSD_GATEWAY_ALLOWED_IPS=10.0.0.1,10.0.0.2
USSD_GATEWAY_SIGNATURE_HEADER=X-Signature
USSD_GATEWAY_SIGNATURE_SECRET=your-secret
```

### `ussd.rate-limit` - Per-Phone Rate Limiting

Applies rate limiting per phone number extracted from the USSD request.

```php
Route::post('/ussd', [UssdController::class, 'handle'])
    ->middleware('ussd.rate-limit');
```

Configure limits in `.env`:

```env
USSD_RATE_LIMIT_PER_MINUTE=10
USSD_RATE_LIMIT_PER_HOUR=100
USSD_RATE_LIMIT_PER_DAY=500
```

### Combining Middleware

```php
Route::post('/ussd', [UssdController::class, 'handle'])
    ->middleware(['ussd.auth', 'ussd.rate-limit']);
```

---

## Internationalization (i18n)

### Configuration

```env
USSD_DEFAULT_LOCALE=en
```

In `config/ussd.php`:

```php
'localization' => [
    'default_locale' => 'en',
    'supported_locales' => ['en', 'sw', 'fr'],
],
```

### Publishing Language Files

```bash
php artisan vendor:publish --tag=ussd-lang
```

This copies language files to `lang/vendor/ussd/` where you can add translations.

### Per-Session Language Selection

```php
use Moffhub\Ussd\Services\TranslationService;

$translation = app(TranslationService::class);
$translation->setSessionLocale($session, 'sw');

// Translate using session locale
$message = $translation->translateForSession('navigation.back', $session);
```

### Helper Function

```php
// In menus and actions
$message = __ussd('errors.invalid_option');
$message = __ussd('navigation.page_info', ['current' => '1', 'total' => '5']);
$message = __ussd('validation.min_length', ['min' => '3'], 'sw');
```

### Custom Provider Creation Guide

1. Extend `AbstractUssdProvider`:

```php
use Moffhub\Ussd\Providers\AbstractUssdProvider;

class MyProvider extends AbstractUssdProvider
{
    protected string $name = 'my_provider';
    protected int $maxMessageLength = 160;

    public function getPhoneNumber(Request $request): string
    {
        return $this->formatPhoneNumber($request->input('mobile'), '254');
    }

    public function getUserInput(Request $request): string
    {
        return $request->input('user_input') ?? '';
    }

    public function getSessionId(Request $request): ?string
    {
        return $request->input('session_id');
    }

    public function getServiceCode(Request $request): ?string
    {
        return $request->input('service_code');
    }
}
```

2. Register with the factory:

```php
ProviderFactory::register('my_provider', MyProvider::class);
```

3. Add provider secret for signature verification:

```php
// config/ussd.php
'providers' => [
    'my_provider' => [
        'secret' => env('USSD_MY_PROVIDER_SECRET'),
    ],
],
```
