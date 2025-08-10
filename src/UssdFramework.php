<?php
namespace Moffhub\Ussd;
use App\Libraries\Ussd\Security\UssdRateLimiter;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Moffhub\Ussd\Analytics\UssdAnalytics;
use Moffhub\Ussd\Cache\UssdCacheManager;
use Moffhub\Ussd\Interfaces\UssdMenuInterface;
use Moffhub\Ussd\Security\UssdAuditLogger;
use Moffhub\Ussd\Security\UssdInputSanitizer;
use Moffhub\Ussd\Services\UssdDatabaseService;

class UssdFramework
{
    /** @var array<string, UssdMenuInterface> */
    protected array $menus = [];
    protected UssdSession $session;
    protected ?Request $request = null;
    /** @var array<string, mixed> */
    protected array $config;
    /** @var array<string, array<callable>> */
    protected array $hooks = [];

    // Enhanced components
    protected ?UssdCacheManager $cacheManager = null;
    protected ?UssdRateLimiter $rateLimiter = null;
    protected ?UssdInputSanitizer $inputSanitizer = null;
    protected ?UssdAuditLogger $auditLogger = null;
    protected ?UssdAnalytics $analytics = null;
    protected ?UssdDatabaseService $databaseService = null;

    protected float $startTime;
    protected array $performanceMetrics = [];
    protected bool $sessionContinuationEnabled = true;
    protected array $sessionValidators = [];
    protected array $sessionRecoveryHandlers = [];

    public function __construct(
        array $config = [],
    )
    {
        $this->startTime = microtime(true);
        $this->config = $this->mergeDefaultConfig($config);
        $this->initializeComponents();
        $this->registerDEfaultSessionHandlers();
    }

    protected function mergeDefaultConfig(array $config): array
    {
        return array_merge([
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
        ], $config);
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
            $this->rateLimiter = new UssdRateLimiter;
            if ($this->databaseService) {
                $this->rateLimiter->setDatabaseService($this->databaseService);
            }
        }

        if ($this->config['security']['input_sanitization']) {
            $this->inputSanitizer = new UssdInputSanitizer;
        }

        if ($this->config['security']['audit_logging']) {
            $this->auditLogger = new UssdAuditLogger;
            if ($this->databaseService) {
                $this->auditLogger->setDatabaseService($this->databaseService);
            }
        }

        if ($this->config['analytics']['enabled']) {
            $this->analytics = new UssdAnalytics($this->config['analytics']);
            if ($this->databaseService) {
                $this->analytics->setDatabaseService($this->databaseService);
            }
        }
    }

    protected function registerDefaultSessionHandlers(): void
    {
        // Session validators
        $this->sessionValidators['timeout'] = function ($sessionData) {
            $updatedAt = Carbon::parse($sessionData['updated_at']);
            return $updatedAt->addSeconds($this->config['session_timeout'])->isAfter(Carbon::now());
        };

        $this->sessionValidators['grace_period'] = function ($sessionData) {
            $updatedAt = Carbon::parse($sessionData['updated_at']);
            return $updatedAt->addSeconds($this->config['grace_period'])->isAfter(Carbon::now());
        };

        // Recovery handlers
        $this->sessionRecoveryHandlers['form_recovery'] = function ($sessionData) {
            if (!empty($sessionData['form_data'])) {
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

        $this->sessionRecoveryHandlers['transaction_recovery'] = function ($sessionData) {
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

    public function handle(Request $request): UssdResponse
    {
        $this->request = $request;
        $phoneNumber = $request->input('phoneNumber');
        $userInput = $request->input('text', '');

        try {
            $requestStartTime = microtime(true);

            // Security checks
            if ($this->rateLimiter && !$this->rateLimiter->allow($phoneNumber)) {
                $this->auditLogger?->logSecurity('rate_limit_exceeded', $phoneNumber);
                return new UssdResponse('END Service temporarily unavailable. Please try again later.', UssdResponse::END);
            }

            if ($this->inputSanitizer) {
                $sanitizationResult = $this->inputSanitizer->sanitize($userInput, 'menu_option');
                if (!$sanitizationResult['valid'] && $sanitizationResult['suspicious']) {
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

            // Initialize session with enhanced capabilities
            $this->session = $this->getOrCreateSession($request);

            // Track session start if new
            if (!$this->session->exists() && $this->analytics) {
                $this->analytics->trackSession($phoneNumber, 'start');
            }

            $this->executeHooks('before_process', [$request, $this->session]);

            // Handle session continuation if needed
            $continuationResponse = $this->handleSessionContinuation();
            if ($continuationResponse) {
                return $continuationResponse;
            }

            // Process the USSD input
            $response = $this->processUssdInput($userInput, $this->session);

            $menuName = $this->session->getCurrentMenu() ?? $this->config['default_menu'];

            // Analytics and logging
            $this->analytics?->trackMenuInteraction($phoneNumber, $menuName, $userInput, [
                'response_type' => $response->getType(),
                'cached' => false,
            ]);

            $this->auditLogger?->logAction('menu_interaction', $phoneNumber, [
                'menu' => $menuName,
                'input' => $userInput,
                'response_type' => $response->getType(),
            ]);

            $this->executeHooks('after_process', [$request, $this->session, $response]);

            // Save session
            $this->session->save();

            // Database operations
            if ($this->databaseService) {
                $this->saveSessionToDatabase($response);
            }

            // Track session end
            if ($response->isEnd() && $this->analytics) {
                $this->analytics->trackSession($phoneNumber, 'end', [
                    'final_menu' => $menuName,
                    'total_interactions' => $this->session->get('interaction_count', 0),
                ]);
            }

            return $response;

        } catch (Exception $e) {
            $this->handleError($e, $phoneNumber, $userInput);
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

    protected function handleExistingSession(array $sessionData, Request $request): UssdSession
    {
        $now = Carbon::now();
        $updatedAt = Carbon::parse($sessionData['updated_at']);

        // Check if session is still active
        if ($this->sessionValidators['timeout']($sessionData)) {
            return $this->createSessionFromData($sessionData, $request, 'active');
        }

        // Check if in grace period
        if ($this->sessionValidators['grace_period']($sessionData)) {
            $session = $this->createSessionFromData($sessionData, $request, 'grace_period');
            $session->setFlag('in_grace_period', true);
            return $session;
        }

        // Attempt intelligent recovery
        if ($this->config['enable_intelligent_recovery']) {
            $recoveryContext = $this->attemptIntelligentRecovery($sessionData, $request);
            if ($recoveryContext) {
                $session = $this->createSessionFromData($sessionData, $request, 'recovered');
                $session->setRecoveryContext($recoveryContext);
                return $session;
            }
        }

        // Create new session with preserved context
        if ($this->config['enable_context_preservation']) {
            return $this->createNewSessionWithContext($sessionData, $request);
        }

        return $this->createNewSession($this->extractPhoneNumber($request), $this->generateSessionId(), $request);
    }

    protected function attemptIntelligentRecovery(array $sessionData, Request $request): ?array
    {
        foreach ($this->sessionRecoveryHandlers as $recoveryType => $handler) {
            $context = $handler($sessionData, $request, $this->config);
            if ($context) {
                return $context;
            }
        }
        return null;
    }

    protected function createSessionFromData(array $sessionData, Request $request, string $status): UssdSession
    {
        $session = new UssdSession($request, $this->config);
        $session->loadFromData($sessionData);
        $session->setStatus($status);
        $session->updateLastAccess();
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

    protected function createNewSessionWithContext(array $expiredSessionData, Request $request): UssdSession
    {
        $session = $this->createNewSession($this->extractPhoneNumber($request), $this->generateSessionId(), $request);

        // Preserve user data
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

    protected function handleSessionContinuation(): ?UssdResponse
    {
        if (!$this->sessionContinuationEnabled) {
            return null;
        }

        $graceMessage = $this->session->getGracePeriodMessage();
        if ($graceMessage) {
            $menuName = $this->session->getCurrentMenu() ?: $this->config['default_menu'];
            $currentMenu = $this->getMenu($menuName);
            $menuResponse = $currentMenu->display($this->session);
            $fullMessage = $graceMessage . "\n\n" . $menuResponse->getMessage();
            return new UssdResponse($fullMessage, $menuResponse->getType());
        }

        $continuationMessage = $this->session->getContinuationMessage();
        if ($continuationMessage) {
            return $this->buildContinuationMenu($continuationMessage);
        }

        return null;
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

        // Handle continuation menu responses
        if ($session->getFlag('awaiting_continuation_choice')) {
            return $this->processContinuationChoice($text);
        }

        if (empty($text)) {
            $currentMenu = $this->getCurrentMenu();
            return $currentMenu->process('', $session);
        }

        $text = trim($text);
        if (empty($text)) {
            $currentMenu = $this->getCurrentMenu();
            return $currentMenu->process('', $session);
        }

        $globalNavResponse = $this->handleGlobalNavigation($text, $session);
        if ($globalNavResponse) {
            return $globalNavResponse;
        }

        $currentMenu = $this->getCurrentMenu();

        try {
            $response = $currentMenu->process($text, $session);
            return $response ?: new UssdResponse('END Service error. Please try again.', UssdResponse::END);
        } catch (Exception $e) {
            Log::error('USSD: Menu processing error', [
                'error' => $e->getMessage(),
                'menu' => $session->getCurrentMenu(),
                'input' => $text,
            ]);
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

    protected function navigateToDefaultMenu(): UssdResponse
    {
        $this->navigateToMenu($this->config['default_menu']);
        return $this->getCurrentMenu()->display($this->session);
    }

    protected function handleGlobalNavigation(string $input, UssdSession $session): ?UssdResponse
    {
        if (!($this->config['global_navigation']['enabled'] ?? true)) {
            return null;
        }

        $navigation = $this->config['navigation'] ?? [];
        $navCommand = $this->extractNavigationCommand($input);

        if (!$navCommand) {
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

    protected function handleHomeNavigation(UssdSession $session): UssdResponse
    {
        $currentMenu = $session->getCurrentMenu();
        $defaultMenu = $this->config['default_menu'];

        if ($currentMenu === $defaultMenu) {
            $menu = $this->getCurrentMenu();
            return $menu->process('', $session);
        }

        $session->reset();
        $this->navigateToMenu($defaultMenu);
        $menu = $this->getCurrentMenu();
        return $menu->process('', $session);
    }

    // Menu management methods
    public function registerMenu(string $name, UssdMenuInterface $menu): static
    {
        $this->menus[$name] = $menu;
        $menu->setFramework($this);

        if (method_exists($menu, 'setCacheManager') && $this->cacheManager) {
            $menu->setCacheManager($this->cacheManager);
        }

        if (method_exists($menu, 'setAnalytics') && $this->analytics) {
            $menu->setAnalytics($this->analytics);
        }

        return $this;
    }

    public function registerMenus(array $menus): static
    {
        foreach ($menus as $name => $menu) {
            $this->registerMenu($name, $menu);
        }
        return $this;
    }

    public function getMenu(string $name): UssdMenuInterface
    {
        if (!isset($this->menus[$name])) {
            throw new Exception("Menu '{$name}' not found");
        }
        return $this->menus[$name];
    }

    public function navigateToMenu(string $menuName, array $data = []): void
    {
        $previousMenu = $this->session->getCurrentMenu();
        $this->session->setCurrentMenu($menuName);
        $this->session->setMenuData($data);

        $this->analytics?->trackUserJourney(
            $this->request->input('phoneNumber'),
            $previousMenu,
            $menuName,
            'navigate'
        );
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

        return $result;
    }

    protected function getCurrentMenu(): UssdMenuInterface
    {
        $menuName = $this->session->getCurrentMenu() ?: $this->config['default_menu'];
        return $this->getMenu($menuName);
    }

    public function getCurrentMenuPublic(): UssdMenuInterface
    {
        return $this->getCurrentMenu();
    }

    // Session management methods
    public function getSession(): ?UssdSession
    {
        return $this->session;
    }

    public function cleanupSessions(): int
    {
        // Implementation depends on persistence strategy
        return 0;
    }

    public function migrateSession(string $fromPhoneNumber, string $toPhoneNumber): bool
    {
        if (!$this->config['enable_session_migration']) {
            return false;
        }
        // Implementation depends on persistence strategy
        return false;
    }

    // Hook management
    public function addHook(string $event, callable $callback): static
    {
        if (!isset($this->hooks[$event])) {
            $this->hooks[$event] = [];
        }
        $this->hooks[$event][] = $callback;
        return $this;
    }

    protected function executeHooks(string $event, array $params = []): void
    {
        if (isset($this->hooks[$event])) {
            foreach ($this->hooks[$event] as $callback) {
                if (is_callable($callback)) {
                    try {
                        call_user_func_array($callback, $params);
                    } catch (Exception $e) {
                        Log::error("Hook execution failed for event: {$event}", [
                            'error' => $e->getMessage(),
                        ]);
                    }
                }
            }
        }
    }

    // Utility methods
    public function getConfig(?string $key = null, mixed $default = null): mixed
    {
        if ($key === null) {
            return $this->config;
        }
        return data_get($this->config, $key, $default);
    }

    public function getCacheManager(): ?UssdCacheManager
    {
        return $this->cacheManager;
    }

    public function getAnalytics(): ?UssdAnalytics
    {
        return $this->analytics;
    }

    public function getDatabaseService(): ?UssdDatabaseService
    {
        return $this->databaseService;
    }

    public function getPerformanceMetrics(): array
    {
        return $this->performanceMetrics;
    }

    public function getHealthStatus(): array
    {
        return [
            'status' => 'healthy',
            'components' => [
                'cache' => $this->cacheManager ? 'enabled' : 'disabled',
                'rate_limiter' => $this->rateLimiter ? 'enabled' : 'disabled',
                'input_sanitizer' => $this->inputSanitizer ? 'enabled' : 'disabled',
                'audit_logger' => $this->auditLogger ? 'enabled' : 'disabled',
                'analytics' => $this->analytics ? 'enabled' : 'disabled',
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

    // Helper methods
    protected function calculateFormCompletionPercentage(array $sessionData): int
    {
        if (!isset($sessionData['form_data']) || !isset($sessionData['form_config'])) {
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

    protected function getPreservableUserData(array $userData): array
    {
        $preservableKeys = ['user_preferences', 'language', 'timezone', 'user_profile', 'account_info'];
        return array_intersect_key($userData, array_flip($preservableKeys));
    }

    protected function extractPhoneNumber(Request $request): string
    {
        return $request->phoneNumber ?? $request->input('phoneNumber') ?? $request->input('msisdn') ?? '';
    }

    protected function generateSessionId(): string
    {
        return uniqid('ussd_', true);
    }

    protected function retrieveSession(string $phoneNumber): ?array
    {
        // This would use the persistence strategy
        // For now, just return null - implement based on your storage choice
        return null;
    }

    protected function trackSessionCreation(string $phoneNumber, string $sessionId): void
    {
        $this->analytics?->trackSession($phoneNumber, 'created', ['session_id' => $sessionId]);
    }

    protected function saveSessionToDatabase(UssdResponse $response): void
    {
        $this->databaseService->saveUserSession(
            $this->session->getSessionId(),
            $this->session->getPhoneNumber(),
            $this->session->getCurrentMenu() ?? $this->config['default_menu'],
            $this->session->getSummary(),
            isset($this->session->getSummary()['created_at']) ? Carbon::parse($this->session->getSummary()['created_at']) : null,
            $response->isEnd()
        );
    }

    protected function handleError(Exception $e, string $phoneNumber, string $userInput): void
    {
        $sessionContext = $this->session ? [
            'session_id' => $this->session->getSessionId(),
            'current_menu' => $this->session->getCurrentMenu(),
            'step' => $this->session->getStep(),
            'status' => $this->session->getStatus(),
            'is_recovered' => $this->session->isRecovered(),
        ] : [];

        Log::error('Unified USSD Framework Error', [
            'error' => $e->getMessage(),
            'phone' => $phoneNumber,
            'input' => $userInput,
            'session_context' => $sessionContext,
            'trace' => $e->getTraceAsString(),
        ]);

        $this->executeHooks('on_error', [$e, $this->request, $this->session, $sessionContext]);
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
                'menu' => $this->session?->getCurrentMenu(),
                'input' => $userInput,
            ]);
        }
    }

    protected function trackPerformance(string $action, float $duration): void
    {
        if ($this->config['performance']['enable_profiling']) {
            $this->analytics?->trackPerformance($action, $duration, [
                'menu' => $this->session?->getCurrentMenu(),
                'memory_usage' => memory_get_usage(true),
            ]);
        }

        $this->performanceMetrics[$action] = $duration;
    }
}
