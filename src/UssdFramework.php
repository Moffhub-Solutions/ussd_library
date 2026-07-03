<?php

namespace Moffhub\Ussd;

use BackedEnum;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Moffhub\Ussd\Analytics\UssdAnalytics;
use Moffhub\Ussd\Cache\UssdCacheManager;
use Moffhub\Ussd\Events\InputReceived;
use Moffhub\Ussd\Events\MenuEntered;
use Moffhub\Ussd\Events\MenuExited;
use Moffhub\Ussd\Events\NavigationPerformed;
use Moffhub\Ussd\Events\SessionEnded;
use Moffhub\Ussd\Events\SessionExpired;
use Moffhub\Ussd\Events\SessionResumed;
use Moffhub\Ussd\Events\SessionStarted;
use Moffhub\Ussd\Interfaces\AuditLoggerInterface;
use Moffhub\Ussd\Interfaces\DeclaresNavigationTargets;
use Moffhub\Ussd\Interfaces\InputSanitizerInterface;
use Moffhub\Ussd\Interfaces\MenuNameInterface;
use Moffhub\Ussd\Interfaces\MetricsRecorderInterface;
use Moffhub\Ussd\Interfaces\RateLimiterInterface;
use Moffhub\Ussd\Interfaces\UssdMenuInterface;
use Moffhub\Ussd\Interfaces\UssdProviderInterface;
use Moffhub\Ussd\Metrics\NullMetricsRecorder;
use Moffhub\Ussd\Providers\ProviderFactory;
use Moffhub\Ussd\Security\UssdAuditLogger;
use Moffhub\Ussd\Security\UssdInputSanitizer;
use Moffhub\Ussd\Security\UssdRateLimiter;
use Moffhub\Ussd\Services\UssdDatabaseService;
use Moffhub\Ussd\Support\UssdConfig;

/**
 * USSD Framework - Main Orchestrator.
 *
 * The central class that coordinates all USSD application functionality:
 * - Request handling and response generation
 * - Menu registration and navigation
 * - Session management with recovery
 * - Provider detection and normalization
 * - Security (rate limiting, input sanitization, audit logging)
 * - Analytics and performance tracking
 * - Hook system for extensibility
 *
 * Basic Usage:
 * ```php
 * $framework = new UssdFramework(['default_menu' => 'main']);
 * $framework->registerMenu('main', new SimpleMenu('Welcome', [...]));
 * $response = $framework->handle($request);
 * ```
 */
class UssdFramework
{
    protected ?UssdAnalytics $analytics = null;

    protected ?AuditLoggerInterface $auditLogger = null;

    protected ?UssdCacheManager $cacheManager = null;

    /** @var array<string, mixed> */
    protected array $config;

    protected ?UssdDatabaseService $databaseService = null;

    // Enhanced components
    /** @var array<string, array<callable>> */
    protected array $hooks = [];

    protected ?InputSanitizerInterface $inputSanitizer = null;

    /** @var array<string, UssdMenuInterface> */
    protected array $menus = [];

    protected array $performanceMetrics = [];

    protected ?RateLimiterInterface $rateLimiter = null;

    protected MetricsRecorderInterface $metrics;

    protected Request $request;

    protected UssdSession $session;

    protected bool $sessionContinuationEnabled = true;

    protected array $sessionRecoveryHandlers = [];

    protected array $sessionValidators = [];

    protected float $startTime;

    protected ?UssdProviderInterface $provider = null;

    public function __construct(
        array $config = [],
    ) {
        $this->startTime = microtime(true);
        $this->config = $this->mergeDefaultConfig($config);
        $this->initializeComponents();
        $this->registerDefaultSessionHandlers();
    }

    protected function mergeDefaultConfig(array $config): array
    {
        $defaults = [
            // When true, process() rethrows menu exceptions instead of masking
            // them behind the generic "Service temporarily unavailable" END.
            // Keep false in production; enable in dev/test to see the real cause.
            // (The on_error hook fires either way, see addHook('on_error', ...).)
            'debug' => false,

            // When true, UssdBuilder::build() asserts every declared navigation
            // target resolves to a registered menu (fails fast on typos/renames).
            'validate_menu_references' => true,

            // Basic framework config
            'session_timeout' => 300,
            'session_prefix' => 'ussd_session_',
            'default_menu' => 'main',
            'sms_length' => 160,
            'reserve_chars' => 50,
            'navigation' => [
                'back' => '99',
                'home' => '0',
                'next' => '00',
                'search' => '98',
            ],
            'global_navigation' => ['enabled' => true],

            // Enhanced session management
            'grace_period' => 600,
            'max_inactive_time' => 1800,
            'persistence_strategy' => 'hybrid',
            'enable_session_migration' => true,
            'enable_context_preservation' => true,
            'enable_intelligent_recovery' => true,
            'cleanup_interval' => 3600,
            'enable_session_analytics' => true,

            // Duplicate-request dedupe: if the same (session, input) arrives again
            // within `window` seconds (a gateway retry), replay the cached response
            // instead of reprocessing. Matters once steps have side effects (STK push).
            'deduplication' => ['enabled' => true, 'window' => 5],

            // Component configuration
            'cache' => ['enabled' => true, 'menu_content_ttl' => 3600, 'data_provider_ttl' => 600],
            'security' => ['rate_limiting' => true, 'input_sanitization' => true, 'audit_logging' => true],
            'analytics' => ['enabled' => true, 'track_user_journey' => true, 'track_performance' => true],
            'performance' => ['enable_profiling' => true, 'slow_query_threshold' => 1000],
            'database' => [
                'enabled' => true,
                'save_rate_limits' => true,
                'save_security_events' => true,
                'save_sessions' => true,
                'save_analytics' => true,
                'save_recovery_logs' => true,
                'save_performance_metrics' => true,
                'anonymize_phone_numbers' => true,
            ],

            // Provider configuration
            'provider' => [
                'default' => 'generic',
                'auto_detect' => true,
                'country_code' => '254',
                'max_message_length' => 182,
            ],
        ];

        return array_replace_recursive($defaults, (array) config('ussd', []), $config);
    }

    protected function initializeComponents(): void
    {
        if ($this->config['database']['enabled']) {
            $this->databaseService = new UssdDatabaseService($this->config['database']);
        }

        if ($this->config['cache']['enabled']) {
            $this->cacheManager = new UssdCacheManager($this->config['cache']);
        }

        if ($this->config['security']['rate_limiting']) {
            $this->rateLimiter = $this->resolveComponent(
                RateLimiterInterface::class,
                fn (): UssdRateLimiter => new UssdRateLimiter($this->config['rate_limiting'] ?? [])
            );
            if ($this->databaseService instanceof UssdDatabaseService && method_exists($this->rateLimiter, 'setDatabaseService')) {
                $this->rateLimiter->setDatabaseService($this->databaseService);
            }
        }

        if ($this->config['security']['input_sanitization']) {
            $this->inputSanitizer = $this->resolveComponent(
                InputSanitizerInterface::class,
                fn (): UssdInputSanitizer => new UssdInputSanitizer
            );
        }

        if ($this->config['security']['audit_logging']) {
            $this->auditLogger = $this->resolveComponent(
                AuditLoggerInterface::class,
                fn (): UssdAuditLogger => new UssdAuditLogger
            );
            if ($this->databaseService instanceof UssdDatabaseService && method_exists($this->auditLogger, 'setDatabaseService')) {
                $this->auditLogger->setDatabaseService($this->databaseService);
            }
        }

        if ($this->config['analytics']['enabled']) {
            $this->analytics = new UssdAnalytics($this->config['analytics']);
            if ($this->databaseService instanceof UssdDatabaseService) {
                $this->analytics->setDatabaseService($this->databaseService);
            }
        }

        // Always present (null-object default); bind MetricsRecorderInterface to override.
        $this->metrics = $this->resolveComponent(
            MetricsRecorderInterface::class,
            fn (): NullMetricsRecorder => new NullMetricsRecorder
        );
    }

    /**
     * Cache key for duplicate-request dedupe, or null if dedupe should not apply
     * (disabled, or no session id to key on).
     *
     * The key includes the session position (see sessionPosition()) so it only
     * matches a TRUE gateway retry, i.e. the same input at the same position. A
     * legitimately repeated input at the next step lands at a different position
     * and therefore a different key, so it is processed instead of being served
     * the previous step's cached response. Keying on session state (not the
     * payload) works uniformly for accumulating (Africa's Talking / Safaricom)
     * and stateful (MTN/Airtel) providers, which only send the current keystroke.
     */
    protected function deduplicationKey(string $phoneNumber, string $sessionId, string $userInput, string $position): ?string
    {
        if (! ($this->config['deduplication']['enabled'] ?? true)) {
            return null;
        }

        if ($sessionId === '') {
            return null;
        }

        return 'ussd_dedupe_'.sha1($phoneNumber.'|'.$sessionId.'|'.$position.'|'.$userInput);
    }

    /**
     * A stable fingerprint of where the session currently is: menu step, form
     * field index, form state and current menu. Two requests at the same
     * position with the same input are a retry; the next step changes at least
     * one of these components.
     */
    protected function sessionPosition(UssdSession $session): string
    {
        return $session->getStep().'|'
            .(string) $session->getFormData('_form_field_index', '').'|'
            .(string) $session->getFormData('_form_state', '').'|'
            .(string) ($session->getCurrentMenu() ?? '');
    }

    /**
     * Resolve a swappable component: use an app-provided binding if one exists,
     * otherwise fall back to the config-aware default. This lets an app bind its
     * own implementation without losing this instance's per-instance config.
     *
     * @template T of object
     *
     * @param  class-string<T>  $abstract
     * @param  callable(): T  $default
     * @return T
     */
    protected function resolveComponent(string $abstract, callable $default): object
    {
        if (function_exists('app') && app()->bound($abstract)) {
            return app($abstract);
        }

        return $default();
    }

    /**
     * The metrics recorder (a no-op unless MetricsRecorderInterface is bound).
     */
    public function metrics(): MetricsRecorderInterface
    {
        return $this->metrics;
    }

    /**
     * Override the rate limiter (e.g. a custom RateLimiterInterface).
     */
    public function setRateLimiter(?RateLimiterInterface $rateLimiter): self
    {
        $this->rateLimiter = $rateLimiter;

        return $this;
    }

    /**
     * Override the input sanitizer.
     */
    public function setInputSanitizer(?InputSanitizerInterface $inputSanitizer): self
    {
        $this->inputSanitizer = $inputSanitizer;

        return $this;
    }

    /**
     * Override the audit logger.
     */
    public function setAuditLogger(?AuditLoggerInterface $auditLogger): self
    {
        $this->auditLogger = $auditLogger;

        return $this;
    }

    protected function registerDefaultSessionHandlers(): void
    {
        $this->sessionValidators['timeout'] = function (array $sessionData) {
            $updatedAt = Carbon::parse($sessionData['updated_at']);

            return $updatedAt->addSeconds($this->config['session_timeout'])->isAfter(Carbon::now());
        };

        $this->sessionValidators['grace_period'] = function (array $sessionData) {
            $updatedAt = Carbon::parse($sessionData['updated_at']);

            return $updatedAt->addSeconds($this->config['grace_period'])->isAfter(Carbon::now());
        };

        $this->sessionRecoveryHandlers['form_recovery'] = function (array $sessionData): ?array {
            if (! empty($sessionData['form_data'])) {
                $completionPercentage = $this->calculateFormCompletionPercentage($sessionData);
                if ($completionPercentage > 50) {
                    return [
                        'type' => 'form_recovery',
                        'completion_percentage' => $completionPercentage,
                        'recoverable_data' => $sessionData['form_data'],
                        'suggested_action' => 'continue_form',
                    ];
                }
            }

            return null;
        };

        $this->sessionRecoveryHandlers['transaction_recovery'] = function (array $sessionData): ?array {
            if (isset($sessionData['transaction_context'])) {
                $transactionData = $sessionData['transaction_context'];
                if (isset($transactionData['status']) && $transactionData['status'] === 'pending') {
                    return [
                        'type' => 'transaction_recovery',
                        'transaction_id' => $transactionData['id'] ?? null,
                        'suggested_action' => 'resume_transaction',
                    ];
                }
            }

            return null;
        };
    }

    protected function calculateFormCompletionPercentage(array $sessionData): int
    {
        if (! isset($sessionData['form_data']) || ! isset($sessionData['form_config'])) {
            return 0;
        }

        $formData = $sessionData['form_data'];
        $formConfig = $sessionData['form_config'];

        if (empty($formConfig['fields'])) {
            return 0;
        }

        $totalFields = count($formConfig['fields']);
        $completedFields = count(array_filter($formData));

        return intval(($completedFields / $totalFields) * 100);
    }

    public function addHook(string $event, callable $callback): static
    {
        if (! isset($this->hooks[$event])) {
            $this->hooks[$event] = [];
        }
        $this->hooks[$event][] = $callback;

        return $this;
    }

    public function cleanupSessions(): int
    {
        return 0;
    }

    public function getAnalytics(): ?UssdAnalytics
    {
        return $this->analytics;
    }

    public function getCacheManager(): ?UssdCacheManager
    {
        return $this->cacheManager;
    }

    public function getConfig(?string $key = null, mixed $default = null): mixed
    {
        if ($key === null) {
            return $this->config;
        }

        return data_get($this->config, $key, $default);
    }

    /**
     * A typed, immutable view over the config. Additive: getConfig() still
     * returns the raw array.
     */
    public function config(): UssdConfig
    {
        return UssdConfig::fromArray($this->config);
    }

    public function getCurrentMenuPublic(): UssdMenuInterface
    {
        return $this->getCurrentMenu();
    }

    public function getDatabaseService(): ?UssdDatabaseService
    {
        return $this->databaseService;
    }

    public function getHealthStatus(): array
    {
        return [
            'status' => 'healthy',
            'components' => [
                'cache' => $this->cacheManager instanceof UssdCacheManager ? 'enabled' : 'disabled',
                'rate_limiter' => $this->rateLimiter instanceof RateLimiterInterface ? 'enabled' : 'disabled',
                'input_sanitizer' => $this->inputSanitizer instanceof InputSanitizerInterface ? 'enabled' : 'disabled',
                'audit_logger' => $this->auditLogger instanceof AuditLoggerInterface ? 'enabled' : 'disabled',
                'analytics' => $this->analytics instanceof UssdAnalytics ? 'enabled' : 'disabled',
            ],
            'session_management' => [
                'continuation_enabled' => $this->sessionContinuationEnabled,
                'persistence_strategy' => $this->config['persistence_strategy'],
                'intelligent_recovery' => $this->config['enable_intelligent_recovery'],
                'context_preservation' => $this->config['enable_context_preservation'],
            ],
            'performance' => $this->getPerformanceMetrics(),
            'uptime' => microtime(true) - $this->startTime,
        ];
    }

    public function getPerformanceMetrics(): array
    {
        return $this->performanceMetrics;
    }

    public function handle(Request $request): UssdResponse
    {
        $this->request = $request;

        // Auto-detect or use configured provider
        $this->provider = $this->getProvider($request);

        // Extract normalized data from provider
        $phoneNumber = $this->provider->getPhoneNumber($request);
        $userInput = $this->provider->getUserInput($request);
        $requestStartTime = microtime(true);

        // Populated after the session is loaded (see below): the dedupe key
        // needs the session position so it matches only a true gateway retry,
        // not a legitimately repeated input at the next step.
        $dedupeKey = null;

        try {

            if ($this->rateLimiter && ! $this->rateLimiter->allow($phoneNumber)) {
                $this->auditLogger?->logSecurity('rate_limit_exceeded', $phoneNumber);

                return new UssdResponse('END Service temporarily unavailable. Please try again later.',
                    UssdResponse::END);
            }

            if ($this->inputSanitizer instanceof InputSanitizerInterface) {
                $sanitizationResult = $this->inputSanitizer->sanitize($userInput, 'menu_option');
                if (! $sanitizationResult['valid'] && $sanitizationResult['suspicious']) {
                    $this->auditLogger?->logSecurity('suspicious_input', $phoneNumber, [
                        'input' => $userInput,
                        'reasons' => $sanitizationResult['reasons'],
                    ]);
                    if ($this->config['security']['strict_mode'] ?? false) {
                        return new UssdResponse('END Invalid input detected.', UssdResponse::END);
                    }
                }
                $userInput = $sanitizationResult['input'];
            }

            $this->session = $this->getOrCreateSession($request);

            // Serve a gateway retry (same input at the same session position,
            // within the window) from cache instead of reprocessing, so
            // side-effecting steps run once. Keyed with the loaded session's
            // position so a repeated input at the next step is NOT deduped.
            $dedupeKey = $this->deduplicationKey(
                $phoneNumber,
                $this->session->getSessionId(),
                $userInput,
                $this->sessionPosition($this->session)
            );
            if ($dedupeKey !== null && ($cached = Cache::get($dedupeKey)) instanceof UssdResponse) {
                Log::debug('USSD: duplicate request served from dedupe cache', [
                    'phone' => $phoneNumber,
                    'input' => $userInput,
                ]);

                return $cached;
            }

            if (! $this->session->exists() && $this->analytics) {
                $this->analytics->trackSession($phoneNumber, 'start');
            }

            // Dispatch session lifecycle events
            if ($this->session->getStatus() === 'new') {
                SessionStarted::dispatch(
                    $this->session->getSessionId(),
                    $phoneNumber,
                    $this->provider->getName(),
                );
            } elseif (in_array($this->session->getStatus(), ['active', 'grace_period', 'recovered'], true)) {
                SessionResumed::dispatch(
                    $this->session->getSessionId(),
                    $phoneNumber,
                    $this->session->isRecovered(),
                );
            }

            $this->executeHooks('before_process', [$request, $this->session]);

            $continuationResponse = $this->handleSessionContinuation();
            if ($continuationResponse instanceof UssdResponse) {
                return $continuationResponse;
            }

            $response = $this->processUssdInput($userInput, $this->session);

            $menuName = $this->session->getCurrentMenu() ?? $this->config['default_menu'];

            $this->analytics?->trackMenuInteraction($phoneNumber, $menuName, $userInput, [
                'response_type' => $response->getType(),
                'cached' => false,
            ]);

            $this->metrics->menuEntered($menuName);
            $this->metrics->dwell($menuName, microtime(true) - $requestStartTime);

            // Roll the interaction into the per-menu/day aggregate. access_count is
            // bumped every request; completion_count only on a terminal END response.
            // Drop-off is not knowable inline (an abandoner never sends another
            // request), so it is left to the session sweeper.
            $this->databaseService?->updateMenuStatistics(
                $menuName,
                $userInput !== '' ? $userInput : null,
                $response->isEnd(),
                false,
                $this->session->getSessionDuration(),
            );

            $this->auditLogger?->logAction('menu_interaction', $phoneNumber, [
                'menu' => $menuName,
                'input' => $userInput,
                'response_type' => $response->getType(),
            ]);

            $this->executeHooks('after_process', [$request, $this->session, $response]);

            $this->session->save();

            if ($this->databaseService instanceof UssdDatabaseService) {
                $this->saveSessionToDatabase($response);
            }

            if ($response->isEnd()) {
                $this->metrics->menuCompleted($menuName);

                $this->analytics?->trackSession($phoneNumber, 'end', [
                    'final_menu' => $menuName,
                    'total_interactions' => $this->session->get('interaction_count', 0),
                ]);

                $menusVisited = array_map(
                    fn (array $entry) => $entry['menu'] ?? '',
                    $this->session->get('menu_history', [])
                );

                SessionEnded::dispatch(
                    $this->session->getSessionId(),
                    $phoneNumber,
                    $this->session->getSessionDuration(),
                    $menusVisited,
                );
            }

            if ($dedupeKey !== null) {
                Cache::put($dedupeKey, $response, (int) ($this->config['deduplication']['window'] ?? 5));
            }

            return $response;

        } catch (Exception $e) {
            $this->handleError($e, $phoneNumber, $userInput);

            // handleError has logged and fired the on_error hook. In debug mode
            // surface the real cause instead of masking it, so dev/test do not
            // have to reconstruct it from logs.
            if ($this->config['debug'] ?? false) {
                throw $e;
            }

            return new UssdResponse('END Service temporarily unavailable. Please try again.', UssdResponse::END);
        } finally {
            $this->performCleanup($requestStartTime, $phoneNumber, $userInput);
        }
    }

    protected function getOrCreateSession(Request $request): UssdSession
    {
        $phoneNumber = $this->extractPhoneNumber($request);
        $sessionId = $request->sessionId ?? $this->generateSessionId();

        $existingSession = $this->retrieveSession($phoneNumber);

        if ($existingSession) {
            return $this->handleExistingSession($existingSession, $request);
        }

        return $this->createNewSession($phoneNumber, $sessionId, $request);
    }

    protected function extractPhoneNumber(Request $request): string
    {
        if ($this->provider instanceof UssdProviderInterface) {
            return $this->provider->getPhoneNumber($request);
        }

        return $request->phoneNumber ?? $request->input('phoneNumber') ?? $request->input('msisdn') ?? '';
    }

    /**
     * Get the USSD provider for the request.
     */
    protected function getProvider(Request $request): UssdProviderInterface
    {
        if ($this->provider instanceof UssdProviderInterface) {
            return $this->provider;
        }

        return ProviderFactory::detect($request, $this->config['provider'] ?? []);
    }

    /**
     * Set a specific provider to use.
     */
    public function setProvider(UssdProviderInterface $provider): self
    {
        $this->provider = $provider;

        return $this;
    }

    /**
     * Get the current provider.
     */
    public function getCurrentProvider(): ?UssdProviderInterface
    {
        return $this->provider;
    }

    /**
     * Format the response using the provider.
     */
    public function formatProviderResponse(UssdResponse $response): string
    {
        if ($this->provider instanceof UssdProviderInterface) {
            return $this->provider->formatResponse($response);
        }

        $prefix = $response->isContinue() ? 'CON ' : 'END ';

        return $prefix.$response->getMessage();
    }

    protected function generateSessionId(): string
    {
        return uniqid('ussd_', true);
    }

    protected function retrieveSession(string $phoneNumber): ?array
    {
        $cacheKey = ($this->config['session_prefix'] ?? 'ussd_session_').$phoneNumber;

        // 1. Try cache first (primary)
        $sessionData = null;
        if ($this->cacheManager instanceof UssdCacheManager || $this->config['cache']['enabled']) {
            $cached = Cache::get($cacheKey);
            if (is_array($cached)) {
                $sessionData = $cached;
            }
        }

        // 2. Fall back to database if cache miss
        if ($sessionData === null && $this->databaseService instanceof UssdDatabaseService) {
            try {
                $dbSession = DB::table('ussd_user_sessions')
                    ->where('phone_number', $phoneNumber)
                    ->where('completed', false)
                    ->orderByDesc('last_activity')
                    ->first();

                if ($dbSession) {
                    $decoded = json_decode((string) $dbSession->session_data, true);
                    if (is_array($decoded)) {
                        $sessionData = $decoded;
                        // Re-populate cache from database
                        $timeout = $this->config['session_timeout'] ?? 300;
                        Cache::put($cacheKey, $sessionData, $timeout);
                    }
                }
            } catch (Exception $e) {
                Log::warning('Failed to retrieve session from database', [
                    'phone' => $phoneNumber,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        if ($sessionData === null) {
            return null;
        }

        // 3. Check if session is expired beyond grace period recovery
        if (isset($sessionData['updated_at'])) {
            $updatedAt = Carbon::parse($sessionData['updated_at']);
            $maxInactiveTime = $this->config['max_inactive_time'] ?? 1800;

            // Beyond max inactive time — not recoverable at all
            if ($updatedAt->addSeconds($maxInactiveTime)->isBefore(Carbon::now())) {
                return null;
            }
        }

        return $sessionData;
    }

    protected function handleExistingSession(array $sessionData, Request $request): UssdSession
    {
        Carbon::now();
        Carbon::parse($sessionData['updated_at']);

        if ($this->sessionValidators['timeout']($sessionData)) {
            return $this->createSessionFromData($sessionData, $request, 'active');
        }

        if ($this->sessionValidators['grace_period']($sessionData)) {
            $session = $this->createSessionFromData($sessionData, $request, 'grace_period');
            $session->setFlag('in_grace_period', true);

            return $session;
        }

        if ($this->config['enable_intelligent_recovery']) {
            $recoveryContext = $this->attemptIntelligentRecovery($sessionData, $request);
            if ($recoveryContext) {
                $session = $this->createSessionFromData($sessionData, $request, 'recovered');
                $session->setRecoveryContext($recoveryContext);

                return $session;
            }
        }

        // Session has expired beyond grace period and intelligent recovery
        SessionExpired::dispatch(
            $sessionData['session_metadata']['session_id'] ?? $this->generateSessionId(),
            $this->extractPhoneNumber($request),
            $sessionData['current_menu'] ?? null,
        );

        if ($this->config['enable_context_preservation']) {
            return $this->createNewSessionWithContext($sessionData, $request);
        }

        return $this->createNewSession($this->extractPhoneNumber($request), $this->generateSessionId(), $request);
    }

    protected function createSessionFromData(array $sessionData, Request $request, string $status): UssdSession
    {
        $session = new UssdSession($request, $this->config);
        $session->loadFromData($sessionData);
        $session->setStatus($status);
        $session->updateLastAccess();

        return $session;
    }

    protected function attemptIntelligentRecovery(array $sessionData, Request $request): ?array
    {
        foreach ($this->sessionRecoveryHandlers as $handler) {
            $context = $handler($sessionData);
            if ($context) {
                return $context;
            }
        }

        return null;
    }

    protected function createNewSessionWithContext(array $expiredSessionData, Request $request): UssdSession
    {
        $session = $this->createNewSession($this->extractPhoneNumber($request), $this->generateSessionId(), $request);

        if (isset($expiredSessionData['user_data'])) {
            $preservableData = $this->getPreservableUserData($expiredSessionData['user_data']);
            foreach ($preservableData as $key => $value) {
                $session->setUserData($key, $value);
            }
        }

        $session->setFlag('session_resumed', true);
        $session->setFlag('context_preserved', true);

        return $session;
    }

    protected function createNewSession(string $phoneNumber, string $sessionId, Request $request): UssdSession
    {
        $session = new UssdSession($request, $this->config);
        $session->setStatus('new');

        if ($this->config['enable_session_analytics']) {
            $this->trackSessionCreation($phoneNumber, $sessionId);
        }

        return $session;
    }

    protected function trackSessionCreation(string $phoneNumber, string $sessionId): void
    {
        $this->analytics?->trackSession($phoneNumber, 'created', ['session_id' => $sessionId]);
    }

    protected function getPreservableUserData(array $userData): array
    {
        $preservableKeys = ['user_preferences', 'language', 'timezone', 'user_profile', 'account_info'];

        return array_intersect_key($userData, array_flip($preservableKeys));
    }

    protected function executeHooks(string $event, array $params = []): void
    {
        if (isset($this->hooks[$event])) {
            foreach ($this->hooks[$event] as $callback) {
                try {
                    call_user_func_array($callback, $params);
                } catch (\Throwable $e) {
                    Log::error("Hook execution failed for event: {$event}", [
                        'error' => $e->getMessage(),
                    ]);

                    // Honor the same debug contract as handle(): surface a
                    // throwing hook in dev/test instead of silently degrading.
                    if ($this->config['debug'] ?? false) {
                        throw $e;
                    }
                }
            }
        }
    }

    protected function handleSessionContinuation(): ?UssdResponse
    {
        if (! $this->sessionContinuationEnabled) {
            return null;
        }

        $graceMessage = $this->session->getGracePeriodMessage();
        if ($graceMessage) {
            $menuName = $this->session->getCurrentMenu() ?: $this->config['default_menu'];
            $currentMenu = $this->getMenu($menuName);
            $menuResponse = $currentMenu->display($this->session);
            $fullMessage = $graceMessage."\n\n".$menuResponse->getMessage();

            return new UssdResponse($fullMessage, $menuResponse->getType());
        }

        $continuationMessage = $this->session->getContinuationMessage();
        if ($continuationMessage) {
            return $this->buildContinuationMenu($continuationMessage);
        }

        return null;
    }

    protected function getCurrentMenu(): UssdMenuInterface
    {
        $menuName = $this->session->getCurrentMenu() ?: $this->config['default_menu'];

        return $this->getMenu($menuName);
    }

    /**
     * Get a registered menu by name.
     *
     * @param  string|MenuNameInterface|BackedEnum  $name  Menu name or enum
     *
     * @throws Exception If menu is not found
     */
    public function getMenu(string|MenuNameInterface|BackedEnum $name): UssdMenuInterface
    {
        $menuKey = $this->resolveMenuName($name);

        if (! isset($this->menus[$menuKey])) {
            throw new Exception("Menu '{$menuKey}' not found");
        }

        return $this->menus[$menuKey];
    }

    /**
     * Check if a menu is registered.
     *
     * @param  string|MenuNameInterface|BackedEnum  $name  Menu name or enum
     */
    public function hasMenu(string|MenuNameInterface|BackedEnum $name): bool
    {
        return isset($this->menus[$this->resolveMenuName($name)]);
    }

    /**
     * Resolve menu name from string or enum.
     *
     * @param  string|MenuNameInterface|BackedEnum  $name  Menu name or enum
     */
    protected function resolveMenuName(string|MenuNameInterface|BackedEnum $name): string
    {
        if ($name instanceof MenuNameInterface) {
            return $name->value();
        }

        if ($name instanceof BackedEnum) {
            return (string) $name->value;
        }

        return $name;
    }

    /**
     * Assert every declared navigation target resolves to a registered menu.
     *
     * Catches typos and renames at build time (a clear exception) instead of a
     * blank screen or generic error at runtime. Only inspects menus that declare
     * their targets (DeclaresNavigationTargets); navigation inside closures is
     * opaque and not checked.
     *
     * @throws \InvalidArgumentException if any target is missing.
     */
    public function validateMenuReferences(): void
    {
        $problems = [];

        foreach ($this->menus as $menuName => $menu) {
            if (! $menu instanceof DeclaresNavigationTargets) {
                continue;
            }

            foreach ($menu->navigationTargets() as $target) {
                $targetName = $this->resolveMenuName($target);

                if (! $this->hasMenu($targetName)) {
                    $problems[] = "menu '{$targetName}' referenced by '{$menuName}' but not registered";
                }
            }
        }

        if ($problems !== []) {
            throw new \InvalidArgumentException(
                'USSD menu navigation validation failed: '.implode('; ', $problems).'.'
            );
        }
    }

    protected function buildContinuationMenu(string $message): UssdResponse
    {
        $recoveryContext = $this->session->getRecoveryContext();

        if ($recoveryContext) {
            return match ($recoveryContext['type']) {
                'form_recovery' => new UssdResponse(
                    "$message\n\n1. Continue form\n2. Start over\n99. Cancel",
                    UssdResponse::CONTINUE
                ),
                'transaction_recovery' => new UssdResponse(
                    "$message\n\n1. Continue transaction\n2. Cancel transaction\n99. Start over",
                    UssdResponse::CONTINUE
                ),
                default => new UssdResponse(
                    "$message\n\n1. Continue\n2. Start over",
                    UssdResponse::CONTINUE
                ),
            };
        }

        return new UssdResponse("$message\n\n1. Continue\n2. Start over", UssdResponse::CONTINUE);
    }

    protected function processUssdInput(string $text, UssdSession $session): UssdResponse
    {
        Log::debug('USSD: Processing input', [
            'raw_text' => $text,
            'phone' => $session->getPhoneNumber(),
            'current_menu' => $session->getCurrentMenu(),
            'session_step' => $session->getStep(),
        ]);

        InputReceived::dispatch(
            $session->getSessionId(),
            $session->getCurrentMenu() ?? $this->config['default_menu'],
            $text,
        );

        if ($session->getFlag('awaiting_continuation_choice')) {
            return $this->processContinuationChoice($text);
        }

        if ($text === '' || $text === '0') {
            $currentMenu = $this->getCurrentMenu();

            return $currentMenu->process('', $session);
        }

        $text = trim($text);
        if ($text === '' || $text === '0') {
            $currentMenu = $this->getCurrentMenu();

            return $currentMenu->process('', $session);
        }

        $globalNavResponse = $this->handleGlobalNavigation($text, $session);
        if ($globalNavResponse instanceof UssdResponse) {
            return $globalNavResponse;
        }

        $currentMenu = $this->getCurrentMenu();

        try {
            return $currentMenu->process($text, $session);
        } catch (Exception $e) {
            Log::error('USSD: Menu processing error', [
                'error' => $e->getMessage(),
                'menu' => $session->getCurrentMenu(),
                'input' => $text,
            ]);

            // In debug mode surface the real cause instead of the generic error,
            // so dev/test do not have to reconstruct it from logs.
            if ($this->config['debug'] ?? false) {
                throw $e;
            }

            return new UssdResponse('CON Service error occurred. Please try again.', UssdResponse::CONTINUE);
        }
    }

    protected function processContinuationChoice(string $input): UssdResponse
    {
        $this->session->setFlag('awaiting_continuation_choice', false);
        $recoveryContext = $this->session->getRecoveryContext();

        return match ($input) {
            '1' => $recoveryContext ? $this->executeContinuationAction($recoveryContext['suggested_action']) : $this->navigateToDefaultMenu(),
            '2' => $this->startOver(),
            '99' => $this->cancel(),
            default => $this->buildContinuationMenu($this->session->getContinuationMessage() ?? 'Welcome back!'),
        };
    }

    protected function executeContinuationAction(string $action): UssdResponse
    {
        return match ($action) {
            'continue_form' => $this->continueForm(),
            'resume_transaction' => $this->resumeTransaction(),
            'resume_navigation' => $this->resumeNavigation(),
            default => $this->navigateToDefaultMenu(),
        };
    }

    protected function continueForm(): UssdResponse
    {
        $currentMenu = $this->getCurrentMenu();
        $this->session->createContextSnapshot('form_continuation');

        return $currentMenu->display($this->session);
    }

    protected function resumeTransaction(): UssdResponse
    {
        $recoveryContext = $this->session->getRecoveryContext();
        $transactionId = $recoveryContext['transaction_id'] ?? null;

        if ($transactionId) {
            $this->navigateToMenu('transaction_resume', ['transaction_id' => $transactionId]);

            return $this->getCurrentMenu()->display($this->session);
        }

        return $this->navigateToDefaultMenu();
    }

    /**
     * Navigate to a menu.
     *
     * @param  string|MenuNameInterface|BackedEnum  $menuName  Menu name or enum
     * @param  array<string, mixed>  $data  Data to pass to the menu
     */
    public function navigateToMenu(string|MenuNameInterface|BackedEnum $menuName, array $data = []): void
    {
        $resolvedName = $this->resolveMenuName($menuName);
        $previousMenu = $this->session->getCurrentMenu();
        $this->session->setCurrentMenu($resolvedName);
        $this->session->setMenuData($data);

        $this->analytics?->trackUserJourney(
            $this->request->input('phoneNumber'),
            $previousMenu,
            $resolvedName,
            'navigate'
        );

        if ($previousMenu !== null && $previousMenu !== $resolvedName) {
            MenuExited::dispatch(
                $this->session->getSessionId(),
                $previousMenu,
                $resolvedName,
                null,
            );
        }

        MenuEntered::dispatch(
            $this->session->getSessionId(),
            $resolvedName,
            $previousMenu,
        );
    }

    /**
     * Navigate to a menu and return its display response.
     *
     * @param  string|MenuNameInterface|BackedEnum  $menuName  Menu name or enum
     * @param  array<string, mixed>  $data  Data to pass to the menu
     */
    public function navigateToMenuWithResponse(string|MenuNameInterface|BackedEnum $menuName, array $data = []): UssdResponse
    {
        $this->navigateToMenu($menuName, $data);

        return $this->getMenu($menuName)->process('', $this->session);
    }

    protected function navigateToDefaultMenu(): UssdResponse
    {
        $this->navigateToMenu($this->config['default_menu']);

        return $this->getCurrentMenu()->display($this->session);
    }

    protected function resumeNavigation(): UssdResponse
    {
        return $this->getCurrentMenu()->display($this->session);
    }

    protected function startOver(): UssdResponse
    {
        $this->session->reset();

        return $this->navigateToDefaultMenu();
    }

    protected function cancel(): UssdResponse
    {
        $this->session->reset();

        return new UssdResponse('Session cancelled. Returning to main menu.', UssdResponse::CONTINUE);
    }

    protected function handleGlobalNavigation(string $input, UssdSession $session): ?UssdResponse
    {
        if (! ($this->config['global_navigation']['enabled'] ?? true)) {
            return null;
        }

        $navigation = $this->config['navigation'] ?? [];
        $navCommand = $this->extractNavigationCommand($input);

        if (! $navCommand) {
            return null;
        }

        return match ($navCommand) {
            $navigation['back'] ?? '99' => $this->handleBackNavigation($session),
            $navigation['home'] ?? '0' => $this->handleHomeNavigation($session),
            default => null,
        };
    }

    protected function extractNavigationCommand(string $input): ?string
    {
        $navigation = $this->config['navigation'] ?? [];
        $navCommands = array_filter([
            $navigation['back'] ?? '99',
            $navigation['home'] ?? '0',
        ]);

        if (in_array($input, $navCommands)) {
            return $input;
        }

        if (str_contains($input, '*')) {
            $parts = explode('*', $input);
            $lastPart = trim(end($parts));
            if (in_array($lastPart, $navCommands)) {
                return $lastPart;
            }
        }

        return null;
    }

    protected function handleBackNavigation(UssdSession $session): UssdResponse
    {
        $currentMenu = $session->getCurrentMenu();
        $defaultMenu = $this->config['default_menu'];

        if ($currentMenu === $defaultMenu) {
            $menu = $this->getCurrentMenu();

            return $menu->process('', $session);
        }

        if ($session->goBack()) {
            $menu = $this->getCurrentMenu();

            return $menu->process('', $session);
        }

        return $this->handleHomeNavigation($session);
    }

    public function goBack(): bool
    {
        $currentMenu = $this->session->getCurrentMenu();
        $result = $this->session->goBack();
        $newMenu = $this->session->getCurrentMenu();

        if ($result && $this->analytics) {
            $this->analytics->trackUserJourney(
                $this->request->input('phoneNumber'),
                $currentMenu,
                $newMenu,
                'back'
            );
        }

        if ($result) {
            NavigationPerformed::dispatch($this->session->getSessionId(), 'back');
        }

        return $result;
    }

    protected function handleHomeNavigation(UssdSession $session): UssdResponse
    {
        $currentMenu = $session->getCurrentMenu();
        $defaultMenu = $this->config['default_menu'];

        if ($currentMenu === $defaultMenu) {
            $menu = $this->getCurrentMenu();

            return $menu->process('', $session);
        }

        NavigationPerformed::dispatch($session->getSessionId(), 'home');

        $session->reset();
        $this->navigateToMenu($defaultMenu);
        $menu = $this->getCurrentMenu();

        return $menu->process('', $session);
    }

    protected function saveSessionToDatabase(UssdResponse $response): void
    {
        $this->databaseService?->saveUserSession(
            sessionId: $this->session->getSessionId(),
            phoneNumber: $this->session->getPhoneNumber(),
            currentMenu: $this->session->getCurrentMenu() ?? $this->config['default_menu'],
            sessionData: $this->session->getSummary(),
            startedAt: isset($this->session->getSummary()['created_at']) ? Carbon::parse($this->session->getSummary()['created_at']) : null,
            completed: $response->isEnd()
        );
    }

    protected function handleError(Exception $e, string $phoneNumber, string $userInput): void
    {
        // An error can be thrown before the session is created (e.g. during
        // session retrieval). Do not access $this->session unguarded, or the
        // secondary "must not be accessed before initialization" error masks the
        // real cause.
        $sessionContext = isset($this->session) ? [
            'session_id' => $this->session->getSessionId(),
            'current_menu' => $this->session->getCurrentMenu(),
            'step' => $this->session->getStep(),
            'status' => $this->session->getStatus(),
            'is_recovered' => $this->session->isRecovered(),
        ] : ['session' => 'not initialized'];

        Log::error('Unified USSD Framework Error', [
            'error' => $e->getMessage(),
            'phone' => $phoneNumber,
            'input' => $userInput,
            'session_context' => $sessionContext,
            'trace' => $e->getTraceAsString(),
        ]);

        $this->executeHooks('on_error', [$e, $this->request, $this->session ?? null, $sessionContext]);
    }

    protected function performCleanup(float $requestStartTime, string $phoneNumber, string $userInput): void
    {
        $this->analytics?->flushBuffer();

        $totalTime = (microtime(true) - $requestStartTime) * 1000;
        $this->trackPerformance('total_request', $totalTime);

        if ($totalTime > ($this->config['performance']['slow_query_threshold'] ?? 1000)) {
            Log::warning('Slow USSD request detected', [
                'duration_ms' => $totalTime,
                'phone' => $phoneNumber,
                'menu' => isset($this->session) ? $this->session->getCurrentMenu() : null,
                'input' => $userInput,
            ]);
        }
    }

    protected function trackPerformance(string $action, float $duration): void
    {
        if ($this->config['performance']['enable_profiling']) {
            $this->analytics?->trackPerformance($action, $duration, [
                'menu' => isset($this->session) ? $this->session->getCurrentMenu() : null,
                'memory_usage' => memory_get_usage(true),
            ]);
        }

        $this->performanceMetrics[$action] = $duration;
    }

    public function migrateSession(string $fromPhoneNumber, string $toPhoneNumber): bool
    {
        if (! $this->config['enable_session_migration']) {
            return false;
        }

        try {
            $fromCacheKey = ($this->config['session_prefix'] ?? 'ussd_session_').$fromPhoneNumber;
            $toCacheKey = ($this->config['session_prefix'] ?? 'ussd_session_').$toPhoneNumber;

            $sessionData = Cache::get($fromCacheKey);
            if (! is_array($sessionData)) {
                return false;
            }

            $timeout = $this->config['session_timeout'] ?? 300;
            Cache::put($toCacheKey, $sessionData, $timeout);
            Cache::forget($fromCacheKey);

            return true;
        } catch (Exception $e) {
            Log::error('Session migration failed', [
                'from' => $fromPhoneNumber,
                'to' => $toPhoneNumber,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    public function registerMenus(array $menus): static
    {
        foreach ($menus as $name => $menu) {
            $this->registerMenu($name, $menu);
        }

        return $this;
    }

    /**
     * Register a menu with the framework.
     *
     * @param  string|MenuNameInterface|BackedEnum  $name  Menu name or enum
     * @param  UssdMenuInterface  $menu  The menu instance
     */
    public function registerMenu(string|MenuNameInterface|BackedEnum $name, UssdMenuInterface $menu): static
    {
        $resolvedName = $this->resolveMenuName($name);
        $this->menus[$resolvedName] = $menu;
        $menu->setFramework($this);

        if (method_exists($menu, 'setCacheManager') && $this->cacheManager) {
            $menu->setCacheManager($this->cacheManager);
        }

        if (method_exists($menu, 'setAnalytics') && $this->analytics) {
            $menu->setAnalytics($this->analytics);
        }

        return $this;
    }

    /**
     * Set the session instance (useful for testing).
     */
    public function setSession(UssdSession $session): static
    {
        $this->session = $session;

        return $this;
    }

    /**
     * Set the request instance (useful for testing).
     */
    public function setRequest(Request $request): static
    {
        $this->request = $request;

        return $this;
    }

    /**
     * Get the current session instance.
     */
    public function getSession(): ?UssdSession
    {
        return $this->session;
    }
}
