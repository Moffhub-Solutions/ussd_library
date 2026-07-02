<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | USSD Framework Configuration
    |--------------------------------------------------------------------------
    |
    | This file contains all configuration options for the Moffhub USSD
    | framework. Most values can be configured via environment variables.
    |
    */

    /*
    |--------------------------------------------------------------------------
    | Basic Settings
    |--------------------------------------------------------------------------
    */

    // Enable/disable the USSD framework
    'enabled' => env('USSD_ENABLED', true),

    // When true, menu exceptions are rethrown instead of masked behind the
    // generic "Service temporarily unavailable" response. Enable in dev/test.
    'debug' => env('USSD_DEBUG', false),

    // When true, UssdBuilder::build() asserts every declared navigation target
    // resolves to a registered menu, failing fast on typos and renames.
    'validate_menu_references' => env('USSD_VALIDATE_MENU_REFERENCES', true),

    // Duplicate-request dedupe: replay the cached response when the same
    // (session, input) arrives again within `window` seconds (a gateway retry).
    'deduplication' => [
        'enabled' => env('USSD_DEDUPE_ENABLED', true),
        'window' => env('USSD_DEDUPE_WINDOW', 5),
    ],

    // Default menu to display when session starts
    'default_menu' => env('USSD_DEFAULT_MENU', 'main'),

    // Session timeout in seconds
    'session_timeout' => env('USSD_SESSION_TIMEOUT', 300),

    // Session cache key prefix
    'session_prefix' => env('USSD_SESSION_PREFIX', 'ussd_session_'),

    // Maximum SMS message length
    'sms_length' => env('USSD_SMS_LENGTH', 160),

    // Reserved characters for system messages
    'reserve_chars' => env('USSD_RESERVE_CHARS', 50),

    /*
    |--------------------------------------------------------------------------
    | Session Limits
    |--------------------------------------------------------------------------
    */

    'session' => [
        // Session timeout in seconds (used when persisting to cache)
        'timeout' => env('USSD_SESSION_TIMEOUT', 300),

        // Maximum number of interaction history entries to keep
        'max_history' => env('USSD_SESSION_MAX_HISTORY', 50),

        // Maximum number of context snapshots to keep
        'max_snapshots' => env('USSD_SESSION_MAX_SNAPSHOTS', 10),
    ],

    /*
    |--------------------------------------------------------------------------
    | Navigation Configuration
    |--------------------------------------------------------------------------
    */

    'navigation' => [
        'back' => env('USSD_NAV_BACK', '99'),
        'home' => env('USSD_NAV_HOME', '0'),
        'next' => env('USSD_NAV_NEXT', '00'),
        'search' => env('USSD_NAV_SEARCH', '98'),
    ],

    'global_navigation' => [
        'enabled' => env('USSD_GLOBAL_NAV_ENABLED', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Session Management
    |--------------------------------------------------------------------------
    */

    // Grace period for session recovery (seconds)
    'grace_period' => env('USSD_GRACE_PERIOD', 600),

    // Maximum inactive time before session expires (seconds)
    'max_inactive_time' => env('USSD_MAX_INACTIVE_TIME', 1800),

    // Session persistence strategy: 'cache', 'database', 'hybrid'
    'persistence_strategy' => env('USSD_PERSISTENCE_STRATEGY', 'hybrid'),

    // Enable session migration between phone numbers
    'enable_session_migration' => env('USSD_ENABLE_SESSION_MIGRATION', true),

    // Enable context preservation when session expires
    'enable_context_preservation' => env('USSD_ENABLE_CONTEXT_PRESERVATION', true),

    // Enable intelligent session recovery
    'enable_intelligent_recovery' => env('USSD_ENABLE_INTELLIGENT_RECOVERY', true),

    // Cleanup interval for expired sessions (seconds)
    'cleanup_interval' => env('USSD_CLEANUP_INTERVAL', 3600),

    // Enable session analytics tracking
    'enable_session_analytics' => env('USSD_ENABLE_SESSION_ANALYTICS', true),

    /*
    |--------------------------------------------------------------------------
    | Cache Configuration
    |--------------------------------------------------------------------------
    */

    'cache' => [
        'enabled' => env('USSD_CACHE_ENABLED', true),
        'menu_content_ttl' => env('USSD_CACHE_MENU_TTL', 3600),
        'data_provider_ttl' => env('USSD_CACHE_DATA_PROVIDER_TTL', 600),
        'driver' => env('USSD_CACHE_DRIVER'), // Uses default Laravel cache driver if null
    ],

    /*
    |--------------------------------------------------------------------------
    | Security Configuration
    |--------------------------------------------------------------------------
    */

    'security' => [
        // Enable rate limiting
        'rate_limiting' => env('USSD_RATE_LIMITING_ENABLED', true),

        // Enable input sanitization
        'input_sanitization' => env('USSD_INPUT_SANITIZATION_ENABLED', true),

        // Enable audit logging
        'audit_logging' => env('USSD_AUDIT_LOGGING_ENABLED', true),

        // Strict mode - reject suspicious input instead of sanitizing
        'strict_mode' => env('USSD_SECURITY_STRICT_MODE', false),

        // Encrypt session data at rest (form data, context variables)
        'encrypt_session_data' => env('USSD_ENCRYPT_SESSION_DATA', false),

        // Fields to encrypt in session data (dot notation supported)
        // Example: ['form_data.pin', 'form_data.account_number', 'user_data.secret']
        'encrypted_fields' => [],
    ],

    /*
    |--------------------------------------------------------------------------
    | Rate Limiting Configuration
    |--------------------------------------------------------------------------
    */

    'rate_limiting' => [
        'max_requests_per_minute' => env('USSD_RATE_LIMIT_PER_MINUTE', 60),
        'max_requests_per_hour' => env('USSD_RATE_LIMIT_PER_HOUR', 600),
        'max_requests_per_day' => env('USSD_RATE_LIMIT_PER_DAY', 3000),

        // Use database-backed whitelist/blacklist
        'use_database_lists' => env('USSD_RATE_LIMIT_USE_DATABASE_LISTS', true),

        // Cache TTL for database access lists (seconds)
        'access_list_cache_ttl' => env('USSD_ACCESS_LIST_CACHE_TTL', 300),

        // Static whitelist (comma-separated phone numbers)
        'whitelist' => env('USSD_WHITELIST') ? explode(',', (string) env('USSD_WHITELIST')) : [],

        // Static blacklist (comma-separated phone numbers)
        'blacklist' => env('USSD_BLACKLIST') ? explode(',', (string) env('USSD_BLACKLIST')) : [],
    ],

    /*
    |--------------------------------------------------------------------------
    | Analytics Configuration
    |--------------------------------------------------------------------------
    */

    'analytics' => [
        'enabled' => env('USSD_ANALYTICS_ENABLED', true),
        'track_user_journey' => env('USSD_ANALYTICS_TRACK_JOURNEY', true),
        'track_performance' => env('USSD_ANALYTICS_TRACK_PERFORMANCE', true),
        'buffer_size' => env('USSD_ANALYTICS_BUFFER_SIZE', 100),
        'flush_interval' => env('USSD_ANALYTICS_FLUSH_INTERVAL', 60),

        // Flush strategy: 'sync' (immediate), 'async' (queued job), 'shutdown' (on process exit)
        'flush_strategy' => env('USSD_ANALYTICS_FLUSH_STRATEGY', 'sync'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Performance Configuration
    |--------------------------------------------------------------------------
    */

    'performance' => [
        'enable_profiling' => env('USSD_PERFORMANCE_PROFILING_ENABLED', true),
        'slow_query_threshold' => env('USSD_SLOW_QUERY_THRESHOLD', 1000), // milliseconds
    ],

    /*
    |--------------------------------------------------------------------------
    | Database Configuration
    |--------------------------------------------------------------------------
    */

    'database' => [
        'enabled' => env('USSD_DATABASE_ENABLED', true),
        'connection' => env('USSD_DATABASE_CONNECTION'), // Uses default Laravel connection if null
        'save_rate_limits' => env('USSD_DB_SAVE_RATE_LIMITS', true),
        'save_security_events' => env('USSD_DB_SAVE_SECURITY_EVENTS', true),
        'save_sessions' => env('USSD_DB_SAVE_SESSIONS', true),
        'save_analytics' => env('USSD_DB_SAVE_ANALYTICS', true),
        'save_recovery_logs' => env('USSD_DB_SAVE_RECOVERY_LOGS', true),
        'save_performance_metrics' => env('USSD_DB_SAVE_PERFORMANCE_METRICS', true),

        // Anonymize phone numbers for privacy (hash or mask)
        'anonymize_phone_numbers' => env('USSD_DB_ANONYMIZE_PHONES', true),

        // Model namespace — override to use your own models instead of the package defaults
        'model_namespace' => env('USSD_MODEL_NAMESPACE', 'Moffhub\\Ussd\\Models'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Provider Configuration
    |--------------------------------------------------------------------------
    */

    'provider' => [
        // Default provider: 'safaricom', 'airtel', 'mtn', 'generic'
        'default' => env('USSD_PROVIDER_DEFAULT', 'generic'),

        // Auto-detect provider from request
        'auto_detect' => env('USSD_PROVIDER_AUTO_DETECT', true),

        // Default country code (e.g., 254 for Kenya, 234 for Nigeria)
        'country_code' => env('USSD_COUNTRY_CODE', '254'),

        // Maximum message length (carrier-specific)
        'max_message_length' => env('USSD_MAX_MESSAGE_LENGTH', 182),
    ],

    /*
    |--------------------------------------------------------------------------
    | Signature Verification
    |--------------------------------------------------------------------------
    |
    | Enable HMAC signature verification for incoming provider requests.
    | When enabled, requests without valid signatures will be rejected.
    |
    */

    'verify_signatures' => env('USSD_VERIFY_SIGNATURES', false),

    /*
    |--------------------------------------------------------------------------
    | Provider Secrets
    |--------------------------------------------------------------------------
    |
    | Per-provider secrets used for signature verification.
    |
    */

    'providers' => [
        'safaricom' => [
            'secret' => env('USSD_SAFARICOM_SECRET'),
        ],
        'airtel' => [
            'secret' => env('USSD_AIRTEL_SECRET'),
        ],
        'mtn' => [
            'secret' => env('USSD_MTN_SECRET'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Circuit Breaker Configuration
    |--------------------------------------------------------------------------
    |
    | Protects external API calls from cascading failures.
    |
    */

    'circuit_breaker' => [
        // Number of consecutive failures before opening the circuit
        'threshold' => env('USSD_CIRCUIT_BREAKER_THRESHOLD', 5),

        // Seconds to wait before allowing a test request (half-open)
        'cooldown_seconds' => env('USSD_CIRCUIT_BREAKER_COOLDOWN', 60),
    ],

    /*
    |--------------------------------------------------------------------------
    | Localization Configuration
    |--------------------------------------------------------------------------
    |
    | Multi-language support for USSD menus and messages.
    |
    */

    'localization' => [
        // Default locale for USSD messages
        'default_locale' => env('USSD_DEFAULT_LOCALE', 'en'),

        // List of supported locales
        'supported_locales' => ['en'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Gateway Authentication
    |--------------------------------------------------------------------------
    |
    | Configure authentication for incoming USSD gateway requests.
    | The UssdAuthentication middleware checks these settings.
    |
    */

    'gateway_authentication' => [
        'enabled' => env('USSD_GATEWAY_AUTH_ENABLED', false),

        // Allowed gateway IPs (empty array = all IPs allowed when enabled)
        'allowed_ips' => env('USSD_GATEWAY_ALLOWED_IPS')
            ? explode(',', (string) env('USSD_GATEWAY_ALLOWED_IPS'))
            : [],

        // HMAC signature verification header name
        'signature_header' => env('USSD_GATEWAY_SIGNATURE_HEADER'),

        // HMAC signature secret
        'signature_secret' => env('USSD_GATEWAY_SIGNATURE_SECRET'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Logging Configuration
    |--------------------------------------------------------------------------
    */

    /*
    |--------------------------------------------------------------------------
    | Admin API Configuration
    |--------------------------------------------------------------------------
    |
    | Configure the admin REST API for access management,
    | rate limit overrides, and session monitoring.
    |
    */

    'admin' => [
        'enabled' => env('USSD_ADMIN_ENABLED', true),
        'prefix' => env('USSD_ADMIN_PREFIX', 'ussd/admin'),
        'middleware' => ['api', 'auth'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Logging Configuration
    |--------------------------------------------------------------------------
    */

    'logging' => [
        'channel' => env('USSD_LOG_CHANNEL'), // Uses default Laravel log channel if null
        'level' => env('USSD_LOG_LEVEL', 'debug'),

        // Log sensitive data (should be false in production)
        'log_sensitive_data' => env('USSD_LOG_SENSITIVE_DATA', false),
    ],
];
