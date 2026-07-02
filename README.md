# Moffhub USSD Framework

A powerful, enterprise-grade Laravel package for building scalable USSD applications with support for multiple African mobile network providers.

## Features

- **Multi-Provider Support**: Built-in adapters for Safaricom/Africa's Talking, Airtel, MTN, and a generic fallback
- **Menu System**: Simple menus, forms, paginated lists, conditional menus, and multi-step wizards
- **Session Management**: Intelligent session recovery, grace periods, and context preservation
- **Security**: Rate limiting, input sanitization, audit logging, and blacklist/whitelist support
- **Analytics**: Track user journeys, menu interactions, and performance metrics
- **Caching**: Efficient caching layer for menus and data providers
- **Data Providers**: Pluggable data sources (Array, Database, API)

## Requirements

- PHP 8.4+
- Laravel 12.0+

## Installation

```bash
composer require moffhub/ussd
```

Publish the configuration file:

```bash
php artisan vendor:publish --provider="Moffhub\Ussd\UssdServiceProvider"
```

Run migrations (optional, for session persistence and analytics):

```bash
php artisan migrate
```

## Quick Start

### 1. Create a USSD Controller

```php
<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Moffhub\Ussd\UssdFramework;
use Moffhub\Ussd\Menus\SimpleMenu;
use Moffhub\Ussd\UssdResponse;

class UssdController extends Controller
{
    public function handle(Request $request)
    {
        $framework = new UssdFramework([
            'default_menu' => 'main',
        ]);

        // Register menus
        $framework->registerMenu('main', $this->mainMenu());
        $framework->registerMenu('balance', $this->balanceMenu());

        // Handle request and return response
        $response = $framework->handle($request);

        return response($response->formatForNetwork())
            ->header('Content-Type', 'text/plain');
    }

    private function mainMenu(): SimpleMenu
    {
        return new SimpleMenu('Welcome to MyApp', [
            '1' => 'Check Balance',
            '2' => 'Send Money',
            '3' => 'Buy Airtime',
        ], [
            '1' => function ($session, $framework) {
                return $framework->navigateToMenuWithResponse('balance');
            },
        ]);
    }

    private function balanceMenu(): SimpleMenu
    {
        return new SimpleMenu('Your balance is KES 1,500.00', [
            '0' => 'Back to Main Menu',
        ], [
            '0' => function ($session, $framework) {
                return $framework->navigateToMenuWithResponse('main');
            },
        ]);
    }
}
```

### 2. Register the Route

```php
// routes/api.php
Route::post('/ussd', [UssdController::class, 'handle']);
```

## Menu Types

### SimpleMenu

Basic menu with numbered options:

```php
use Moffhub\Ussd\Menus\SimpleMenu;

$menu = new SimpleMenu('Main Menu', [
    '1' => 'Option One',
    '2' => 'Option Two',
    '3' => 'Option Three',
], [
    '1' => function ($session, $framework) {
        return UssdResponse::end('You selected option one!');
    },
]);
```

### FormMenu

Collect multi-step form data:

```php
use Moffhub\Ussd\Menus\FormMenu;
use Moffhub\Ussd\Helpers\FormField;

$formMenu = new FormMenu('Registration');

$formMenu->addField(new FormField(
    name: 'name',
    prompt: 'Enter your full name:',
    validators: [Validators::required(), Validators::minLength(2)]
));

$formMenu->addField(new FormField(
    name: 'phone',
    prompt: 'Enter phone number:',
    validators: [Validators::phone()]
));

$formMenu->setOnComplete(function ($session, $formData) {
    return UssdResponse::end("Thank you, {$formData['name']}! Registration complete.");
});
```

### PaginatedMenu

Display large lists with pagination:

```php
use Moffhub\Ussd\Menus\PaginatedMenu;

$menu = new PaginatedMenu('Select Product', $products, [
    'items_per_page' => 5,
    'item_formatter' => fn($item) => $item['name'] . ' - KES ' . $item['price'],
]);
```

### ConditionalMenu

Show different menus based on conditions:

```php
use Moffhub\Ussd\Menus\ConditionalMenu;

$menu = new ConditionalMenu($defaultMenu);

$menu->addCondition(
    fn($session) => $session->getUserData('role') === 'admin',
    $adminMenu
);

$menu->addCondition(
    fn($session) => $session->getUserData('is_premium'),
    $premiumMenu
);
```

### WizardMenu

Multi-step guided processes:

```php
use Moffhub\Ussd\Menus\WizardMenu;

$wizard = new WizardMenu('Money Transfer');

$wizard->addStep('amount', new SimpleMenu('Enter amount:', ['*' => 'Cancel']));
$wizard->addStep('recipient', new SimpleMenu('Enter recipient:', ['*' => 'Cancel']));
$wizard->addStep('confirm', new SimpleMenu('Confirm transfer?', ['1' => 'Yes', '2' => 'No']));

$wizard->setCompletionHandler(function ($session, $data) {
    // Process the transfer
    return UssdResponse::end('Transfer successful!');
});
```

## Using the MenuBuilder

Fluent interface for building menus:

```php
use Moffhub\Ussd\Builders\MenuBuilder;

$menu = (new MenuBuilder('main_menu'))
    ->title('Welcome')
    ->option('1', 'Check Balance', fn($s, $f) => $f->navigateToMenuWithResponse('balance'))
    ->option('2', 'Send Money')
    ->option('3', 'Exit', fn() => UssdResponse::end('Goodbye!'))
    ->build();
```

## Type-Safe Menu Names with Enums

For better type safety and IDE autocomplete, use enums for menu names:

### Creating Your Menu Enum

```php
<?php

namespace App\Enums;

use Moffhub\Ussd\Interfaces\MenuNameInterface;
use Moffhub\Ussd\Traits\MenuEnumTrait;

enum AppMenu: string implements MenuNameInterface
{
    use MenuEnumTrait;

    case Main = 'main';
    case Balance = 'balance';
    case SendMoney = 'send_money';
    case BuyAirtime = 'buy_airtime';
    case Settings = 'settings';

    public function label(): string
    {
        return match($this) {
            self::Main => 'Main Menu',
            self::Balance => 'Check Balance',
            self::SendMoney => 'Send Money',
            self::BuyAirtime => 'Buy Airtime',
            self::Settings => 'Settings',
        };
    }
}
```

### Using Your Enum

```php
use App\Enums\AppMenu;

// Register menus with enum
$framework->registerMenu(AppMenu::Main, new SimpleMenu('Welcome', [...]));
$framework->registerMenu(AppMenu::Balance, new SimpleMenu('Balance', [...]));

// Navigate with enum
$framework->navigateToMenu(AppMenu::Balance);
$response = $framework->navigateToMenuWithResponse(AppMenu::SendMoney);

// Check if menu exists
if ($framework->hasMenu(AppMenu::Settings)) {
    // ...
}

// Helper methods from MenuEnumTrait
AppMenu::values();           // ['main', 'balance', 'send_money', ...]
AppMenu::hasValue('main');   // true
AppMenu::fromValue('main');  // AppMenu::Main
AppMenu::toSelectOptions();  // ['main' => 'Main Menu', ...]
```

## Provider Adapters

The framework automatically detects the USSD provider from the request, or you can specify one:

```php
use Moffhub\Ussd\Providers\ProviderFactory;

// Auto-detect from request
$provider = ProviderFactory::detect($request);

// Or create a specific provider
$provider = ProviderFactory::create('safaricom');
$provider = ProviderFactory::create('airtel', ['country_code' => '256']);
$provider = ProviderFactory::create('mtn', ['country_code' => '234']);
```

### Supported Providers

| Provider | Aliases | Field Mapping |
|----------|---------|---------------|
| Safaricom | `safaricom`, `africas_talking`, `at` | phoneNumber, text, sessionId |
| Airtel | `airtel` | msisdn, input/text, transactionId |
| MTN | `mtn` | msisdn, UserAnswer, sessionId |
| Generic | `generic` | Auto-detect multiple field names |

### Register Custom Provider

```php
use Moffhub\Ussd\Providers\ProviderFactory;
use Moffhub\Ussd\Providers\AbstractUssdProvider;

class MyCustomProvider extends AbstractUssdProvider
{
    protected string $name = 'custom';

    public function getPhoneNumber(Request $request): string
    {
        return $request->input('mobile_number');
    }

    // ... implement other methods
}

ProviderFactory::register('custom', MyCustomProvider::class);
```

## Data Providers

### ArrayDataProvider

```php
use Moffhub\Ussd\DataProviders\ArrayDataProvider;

$provider = new ArrayDataProvider([
    ['id' => 1, 'name' => 'Product A', 'price' => 100],
    ['id' => 2, 'name' => 'Product B', 'price' => 200],
]);

$menu = new PaginatedMenu('Products', $provider);
```

### DatabaseDataProvider

```php
use Moffhub\Ussd\DataProviders\DatabaseDataProvider;

$provider = new DatabaseDataProvider(Product::class);

// Or with custom query
$provider = new DatabaseDataProvider(Product::class, function () {
    return Product::where('active', true)->orderBy('name');
});
```

### ApiDataProvider

```php
use Moffhub\Ussd\DataProviders\ApiDataProvider;

$provider = new ApiDataProvider(
    'https://api.example.com',
    ['Content-Type: application/json'],
    ['type' => 'bearer', 'token' => 'your-api-token']
);
```

## Session Management

### Accessing Session Data

```php
// In your menu actions
$menu = new SimpleMenu('Menu', ['1' => 'Action'], [
    '1' => function ($session, $framework) {
        // Get session data
        $name = $session->get('name');
        $step = $session->get('step', 0);

        // Set session data
        $session->set('last_action', 'viewed_balance');

        // Form data
        $formData = $session->getFormData();

        // User data (preserved across sessions)
        $userId = $session->getUserData('user_id');

        return UssdResponse::continue("Hello, $name!");
    },
]);
```

### Session Recovery

The framework automatically handles session recovery:

```php
$framework = new UssdFramework([
    'grace_period' => 600, // 10 minutes
    'enable_intelligent_recovery' => true,
    'enable_context_preservation' => true,
]);
```

## Security Features

### Rate Limiting

```php
$framework = new UssdFramework([
    'security' => [
        'rate_limiting' => true,
    ],
]);

// Configure rate limits with static lists
$rateLimiter = new UssdRateLimiter([
    'max_requests_per_minute' => 10,
    'max_requests_per_hour' => 100,
    'max_requests_per_day' => 500,
    'whitelist' => ['+254700000000'],
    'blacklist' => ['+254999999999'],
]);
```

### Database-Backed Whitelist/Blacklist

For production applications, use database-backed access lists that can be managed via a UI:

```php
use Moffhub\Ussd\Security\UssdRateLimiter;
use Moffhub\Ussd\Security\DatabaseAccessListProvider;

// Enable database-backed lists
$rateLimiter = new UssdRateLimiter([
    'use_database_lists' => true,
    'max_requests_per_minute' => 10,
]);

// Or set a custom provider
$rateLimiter->setAccessListProvider(new DatabaseAccessListProvider([
    'cache_ttl' => 300, // Cache for 5 minutes
    'cache_enabled' => true,
]));

// Manage whitelist/blacklist programmatically
$rateLimiter->addToWhitelist('+254712345678', 'VIP customer', 'admin@example.com');
$rateLimiter->addToBlacklist('+254999999999', 'Spam detected', 'system', now()->addDays(7));

$rateLimiter->removeFromWhitelist('+254712345678');
$rateLimiter->removeFromBlacklist('+254999999999');
```

### Using the UssdAccessList Model Directly

Build your own admin UI using the Eloquent model:

```php
use Moffhub\Ussd\Models\UssdAccessList;

// Add to whitelist with expiration
UssdAccessList::addToWhitelist(
    phoneNumber: '+254712345678',
    reason: 'VIP customer',
    addedBy: auth()->user()->email,
    expiresAt: now()->addMonths(6),
    metadata: ['customer_id' => 123]
);

// Add to blacklist
UssdAccessList::addToBlacklist(
    phoneNumber: '+254999999999',
    reason: 'Abuse detected',
    addedBy: 'system'
);

// Query for your admin panel
$whitelist = UssdAccessList::whitelist()->active()->paginate(20);
$blacklist = UssdAccessList::blacklist()->active()->paginate(20);

// Check status
UssdAccessList::isWhitelisted('+254712345678'); // true
UssdAccessList::isBlacklisted('+254999999999'); // true

// Get all numbers
$whitelistedNumbers = UssdAccessList::getWhitelistedNumbers();
$blacklistedNumbers = UssdAccessList::getBlacklistedNumbers();
```

### Custom Access List Provider

Implement your own storage backend:

```php
use Moffhub\Ussd\Interfaces\AccessListProviderInterface;

class RedisAccessListProvider implements AccessListProviderInterface
{
    public function isWhitelisted(string $phoneNumber): bool
    {
        return Redis::sismember('ussd:whitelist', $phoneNumber);
    }

    public function isBlacklisted(string $phoneNumber): bool
    {
        return Redis::sismember('ussd:blacklist', $phoneNumber);
    }

    // ... implement other methods
}

$rateLimiter->setAccessListProvider(new RedisAccessListProvider());
```

### Input Sanitization

```php
$framework = new UssdFramework([
    'security' => [
        'input_sanitization' => true,
        'strict_mode' => false, // Set to true to reject suspicious input
    ],
]);
```

## Validators

Built-in validators for form fields:

```php
use Moffhub\Ussd\Helpers\Validators;

$field = new FormField(
    name: 'email',
    prompt: 'Enter email:',
    validators: [
        Validators::required(),
        Validators::email(),
    ]
);

// Available validators:
Validators::required($message)
Validators::minLength($min, $message)
Validators::maxLength($max, $message)
Validators::length($min, $max, $message)
Validators::numeric($message)
Validators::phone($message)
Validators::email($message)
Validators::inOptions($options, $message)
Validators::regex($pattern, $message)
Validators::dob($message, $minAge)
Validators::custom($callback, $message)
```

## Response Types

```php
use Moffhub\Ussd\UssdResponse;

// Continue session
UssdResponse::continue('Select an option:');

// End session
UssdResponse::end('Thank you for using our service!');

// With menu
UssdResponse::menu('Main Menu', ['1' => 'Option 1', '2' => 'Option 2']);

// Form field
UssdResponse::form('Enter your name:', true, $validationError);

// Pagination
UssdResponse::pagination($content, $currentPage, $totalPages, $hasNext, $hasPrevious);

// Progress indicator
UssdResponse::progress('Processing...', $currentStep, $totalSteps);

// Confirmation
UssdResponse::confirmation('Are you sure?');

// Error
UssdResponse::error('Something went wrong', $endSession);

// Success
UssdResponse::success('Operation completed!');
```

## Configuration

Full configuration options:

```php
$framework = new UssdFramework([
    // Basic settings
    'session_timeout' => 300,
    'session_prefix' => 'ussd_session_',
    'default_menu' => 'main',
    'sms_length' => 160,

    // Navigation
    'navigation' => [
        'back' => '99',
        'home' => '0',
        'next' => '00',
        'search' => '98',
    ],
    'global_navigation' => ['enabled' => true],

    // Session management
    'grace_period' => 600,
    'enable_session_migration' => true,
    'enable_context_preservation' => true,
    'enable_intelligent_recovery' => true,

    // Security
    'security' => [
        'rate_limiting' => true,
        'input_sanitization' => true,
        'audit_logging' => true,
        'strict_mode' => false,
    ],

    // Analytics
    'analytics' => [
        'enabled' => true,
        'track_user_journey' => true,
        'track_performance' => true,
    ],

    // Caching
    'cache' => [
        'enabled' => true,
        'menu_content_ttl' => 3600,
        'data_provider_ttl' => 600,
    ],

    // Database
    'database' => [
        'enabled' => true,
        'save_sessions' => true,
        'save_analytics' => true,
        'anonymize_phone_numbers' => true,
    ],

    // Provider
    'provider' => [
        'default' => 'generic',
        'auto_detect' => true,
        'country_code' => '254',
        'max_message_length' => 182,
    ],
]);
```

### Publishing the config

```bash
php artisan vendor:publish --tag=ussd-config
```

This writes `config/ussd.php`. The published file is the authoritative control
surface: framework defaults are deep-merged with `config('ussd')` and then with
any array passed to `new UssdFramework([...])` (nearest wins, nested blocks merge
recursively rather than replacing wholesale).

### Config reference (selected keys)

| Key | Env var | Default | Purpose |
| --- | --- | --- | --- |
| `debug` | `USSD_DEBUG` | `false` | Rethrow menu exceptions instead of the generic error (dev/test). |
| `validate_menu_references` | `USSD_VALIDATE_MENU_REFERENCES` | `true` | `UssdBuilder::build()` fails fast on navigation to an unregistered menu. |
| `deduplication.enabled` | `USSD_DEDUPE_ENABLED` | `true` | Replay the cached response for a retried `(session, input)`. |
| `deduplication.window` | `USSD_DEDUPE_WINDOW` | `5` | Dedupe window, in seconds. |
| `session_timeout` | `USSD_SESSION_TIMEOUT` | `300` | Inactivity (seconds) before a session expires; also the sweeper's default. |
| `security.rate_limiting` | `USSD_RATE_LIMITING_ENABLED` | `true` | Enable the rate limiter. |
| `rate_limiting.max_requests_per_minute` | `USSD_RATE_LIMIT_PER_MINUTE` | `60` | Per-minute request cap (tuned for interactive menus). |
| `rate_limiting.max_requests_per_hour` | `USSD_RATE_LIMIT_PER_HOUR` | `600` | Per-hour request cap. |
| `rate_limiting.max_requests_per_day` | `USSD_RATE_LIMIT_PER_DAY` | `3000` | Per-day request cap. |
| `rate_limiting.use_database_lists` | `USSD_RATE_LIMIT_USE_DATABASE_LISTS` | `true` | Use the DB-backed whitelist/blacklist (needs the `ussd_access_lists` table). |
| `database.enabled` | `USSD_DATABASE_ENABLED` | `true` | Enable the database layer (sessions, analytics, stats). |

### Session tables

Two tables, different jobs:

- **`ussd_user_sessions`** is the runtime session store: one row per live/finished
  session (menu, session data, activity timestamps, completion). This is what the
  cleanup and sweeper commands operate on, and what you query for live sessions.
- **`ussd_sessions`** is used by access-management/admin tooling, not the normal
  request path, so it is empty in a plain menu deployment. If you are looking for
  "where are my sessions", it is `ussd_user_sessions`.

## Debugging: surface the real error

By default the framework catches menu exceptions and returns a generic message.
Set `debug` (or `USSD_DEBUG=true`) to rethrow the underlying exception so you see
the real cause in dev/test. The `on_error` hook fires either way:

```php
$framework->addHook('on_error', function (\Throwable $e) { /* report */ });
```

## Swapping components

Rate limiter, input sanitizer, audit logger and the session store resolve from
the container. Bind your own to replace the default without forking:

```php
use Moffhub\Ussd\Interfaces\RateLimiterInterface;
use Moffhub\Ussd\Interfaces\SessionStoreInterface;

app()->bind(RateLimiterInterface::class, MyRateLimiter::class);

// Change where live session state is held (default: CacheSessionStore).
app()->bind(SessionStoreInterface::class, MyRedisSessionStore::class);
```

Or via the builder:

```php
UssdBuilder::create()
    ->rateLimiter(new MyRateLimiter())
    ->inputSanitizer(new MyInputSanitizer())
    ->provider(new MyProvider())
    ->build();
```

## Typed config access

`getConfig()` still returns the raw array. `config()` additionally gives a typed,
immutable view with dot-notation and a recursive `merge()` (so a partial override
never wipes sibling keys):

```php
$framework->config()->deduplicationWindow();          // int, default 5
$framework->config()->get('security.rate_limiting');  // dot-notation
$merged = $framework->config()->merge(['security' => ['audit_logging' => false]]);
```

## Metrics

Bind `MetricsRecorderInterface` to receive funnel metrics (menu entered,
completed, abandoned, dwell). Everything is keyed by menu only (never by phone
or session) to keep cardinality bounded. The default is a no-op.

## Testing menus

`UssdTester` drives a session without a gateway and with test-friendly defaults:

```php
use Moffhub\Ussd\Testing\UssdTester;

UssdTester::fake(['default_menu' => 'main'])
    ->register('main', $mainMenu)
    ->register('airtime', $airtimeMenu)
    ->drive(['3', '1'])          // dial, then send each input
    ->assertEnded()
    ->assertSee('successful');
```

## Facade

Use the `Ussd` facade for convenient access:

```php
use Moffhub\Ussd\Facades\Ussd;

$response = Ussd::handle($request);
```

## Internationalization (i18n)

Built-in multi-language support:

```php
// Using the helper function
$message = __ussd('errors.invalid_option');
$message = __ussd('navigation.page_info', ['current' => '1', 'total' => '5']);
$message = __ussd('validation.min_length', ['min' => '3'], 'sw');

// Per-session language selection
use Moffhub\Ussd\Services\TranslationService;

$translation = app(TranslationService::class);
$translation->setSessionLocale($session, 'sw');
$message = $translation->translateForSession('navigation.back', $session);
```

Publish and customize language files:

```bash
php artisan vendor:publish --tag=ussd-lang
```

Configure in `config/ussd.php`:

```php
'localization' => [
    'default_locale' => 'en',
    'supported_locales' => ['en', 'sw', 'fr'],
],
```

## Signature Verification

Verify incoming provider requests using HMAC signatures:

```env
USSD_VERIFY_SIGNATURES=true
USSD_SAFARICOM_SECRET=your-api-key
USSD_AIRTEL_SECRET=your-callback-token
USSD_MTN_SECRET=your-hmac-secret
```

## Circuit Breaker

Protect external API calls from cascading failures:

```env
USSD_CIRCUIT_BREAKER_THRESHOLD=5
USSD_CIRCUIT_BREAKER_COOLDOWN=60
```

The circuit breaker is automatically integrated into `ApiDataProvider`. It supports three states:
- **Closed**: All requests pass through normally
- **Open**: Requests return fallback data (after threshold failures)
- **Half-open**: One test request allowed after cooldown period

## Session Encryption

Encrypt sensitive session data at rest:

```env
USSD_ENCRYPT_SESSION_DATA=true
```

Configure which fields to encrypt in `config/ussd.php`:

```php
'security' => [
    'encrypt_session_data' => true,
    'encrypted_fields' => ['form_data.pin', 'form_data.account_number'],
],
```

## Events

The framework dispatches Laravel events at key lifecycle points:

| Event | Payload |
|-------|---------|
| `SessionStarted` | sessionId, phone, provider |
| `SessionResumed` | sessionId, phone, wasRecovered |
| `SessionEnded` | sessionId, phone, duration, menusVisited |
| `SessionExpired` | sessionId, phone, lastMenu |
| `MenuEntered` | sessionId, menuName, fromMenu |
| `MenuExited` | sessionId, menuName, toMenu, selection |
| `FormSubmitted` | sessionId, menuName, formData |
| `InputReceived` | sessionId, menuName, rawInput |
| `NavigationPerformed` | sessionId, action |

Listen to events:

```php
use Moffhub\Ussd\Events\FormSubmitted;

Event::listen(FormSubmitted::class, function (FormSubmitted $event) {
    ProcessRegistration::dispatch($event->formData);
});
```

## Artisan Commands

```bash
# Clean up expired sessions (delete old rows beyond retention)
php artisan ussd:cleanup-sessions --older-than=24h
php artisan ussd:cleanup-sessions --dry-run

# Finalize abandoned sessions: record drop-off, emit SessionExpired, mark ended.
# Schedule this (e.g. every minute) so abandonment is measurable from events.
php artisan ussd:sweep-sessions
php artisan ussd:sweep-sessions --timeout=180 --dry-run

# Interactive local simulator (walk the menu in the terminal, no phone needed)
php artisan ussd:simulate --phone=254700000000 --provider=safaricom

# List active sessions
php artisan ussd:list-sessions
php artisan ussd:list-sessions --provider=safaricom

# Manage whitelist/blacklist
php artisan ussd:manage-access add --type=whitelist --phone=+254712345678
php artisan ussd:manage-access list --type=blacklist

# Health check
php artisan ussd:health
```

## Middleware

Two middleware are registered automatically:

```php
// Gateway authentication (IP + signature verification)
Route::post('/ussd', [UssdController::class, 'handle'])->middleware('ussd.auth');

// Per-phone rate limiting
Route::post('/ussd', [UssdController::class, 'handle'])->middleware('ussd.rate-limit');

// Combined
Route::post('/ussd', [UssdController::class, 'handle'])->middleware(['ussd.auth', 'ussd.rate-limit']);
```

## Production Deployment

See [docs/PRODUCTION.md](docs/PRODUCTION.md) for a detailed production deployment guide including:

- Deployment checklist
- Cache driver selection (Redis recommended)
- Database migration verification
- Provider credential setup
- Rate limit tuning
- Session timeout configuration
- Analytics retention policy
- Performance tuning guide
- Troubleshooting guide

### Quick Checklist

- [ ] Set cache driver to Redis: `CACHE_DRIVER=redis`
- [ ] Run migrations: `php artisan migrate`
- [ ] Configure provider secrets in `.env`
- [ ] Enable signature verification: `USSD_VERIFY_SIGNATURES=true`
- [ ] Enable session encryption if handling PINs: `USSD_ENCRYPT_SESSION_DATA=true`
- [ ] Schedule session cleanup: `ussd:cleanup-sessions`
- [ ] Start queue worker for async analytics: `USSD_ANALYTICS_FLUSH_STRATEGY=async`
- [ ] Configure gateway IPs: `USSD_GATEWAY_AUTH_ENABLED=true`
- [ ] Verify health: `php artisan ussd:health`

## Local Development

### USSD Simulator

Test your USSD flows interactively from the command line without needing a real gateway:

```bash
php artisan ussd:simulate
```

This starts an interactive CLI session that simulates a USSD flow. It sends requests to your registered menus, displays responses, and prompts for input just like a real USSD session.

#### Options

```bash
# Use a specific phone number
php artisan ussd:simulate --phone=254712345678

# Use a specific provider adapter (safaricom, airtel, mtn, generic)
php artisan ussd:simulate --provider=safaricom

# Use a specific service code
php artisan ussd:simulate --service-code=*456#

# Combine options
php artisan ussd:simulate --phone=254712345678 --provider=safaricom --service-code=*456#
```

#### How It Works

1. The simulator generates a unique session ID and sends an initial request to `UssdFramework::handle()`
2. The response is displayed in the terminal with `[CON]` (continue) or `[END]` (end) indicators
3. If the response is `CON`, you are prompted to enter input
4. The simulator sends a follow-up request with your input
5. This loop continues until the response is `END` or you type `exit`
6. A session summary is displayed at the end with total steps and duration

## Testing

Run the test suite:

```bash
composer test
```

Or with PHPUnit directly:

```bash
./vendor/bin/phpunit
```

## Contributing

1. Fork the repository
2. Create a feature branch
3. Write tests for your changes
4. Submit a pull request

## License

This package is open-sourced software licensed under the [MIT license](LICENSE).

## Credits

- [Moffat Munene](https://github.com/moffhub)
