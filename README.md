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
