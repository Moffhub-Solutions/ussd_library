<?php

namespace Moffhub\Ussd;

use Carbon\Carbon;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Moffhub\Ussd\Interfaces\SessionStoreInterface;
use Moffhub\Ussd\Security\SessionEncryptor;
use Moffhub\Ussd\Session\CacheSessionStore;

/**
 * USSD Session Manager.
 *
 * Manages the lifecycle of a USSD session including:
 * - Session data storage and retrieval
 * - Menu navigation history
 * - Form data collection
 * - Session recovery and grace periods
 * - Context preservation across sessions
 * - Performance metrics tracking
 *
 * Session States:
 * - new: Initial session creation
 * - active: Normal operation (within timeout)
 * - grace_period: Timed out but recoverable
 * - recovered: Restored from expired session
 * - expired: Lost, new session created
 */
class UssdSession
{
    protected string $sessionId;

    protected string $phoneNumber;

    protected array $data;

    protected array $config;

    protected string $cacheKey;

    protected string $status = 'new'; // new, active, grace_period, recovered, expired

    protected ?array $recoveryContext = null;

    protected ?Carbon $lastAccessTime;

    protected array $sessionMetrics = [];

    protected ?SessionEncryptor $encryptor = null;

    protected SessionStoreInterface $store;

    /**
     * Create a new USSD session.
     *
     * @param  Request|string  $phoneNumberOrRequest  Phone number or HTTP request
     * @param  array|string  $configOrSessionId  Configuration array or session ID (when first param is string)
     * @param  array  $config  Configuration array (only used when first param is string)
     */
    public function __construct(Request|string $phoneNumberOrRequest, array|string $configOrSessionId = [], array $config = [])
    {
        if ($phoneNumberOrRequest instanceof Request) {
            // Legacy: new UssdSession($request, $config)
            $this->sessionId = $phoneNumberOrRequest->sessionId ?? uniqid('ussd_', true);
            $this->phoneNumber = $phoneNumberOrRequest->phoneNumber ?? $phoneNumberOrRequest->input('phoneNumber') ?? '';
            $this->config = is_array($configOrSessionId) ? $configOrSessionId : [];
        } else {
            // New: new UssdSession($phoneNumber, $sessionId, $config)
            $this->phoneNumber = $phoneNumberOrRequest;
            $this->sessionId = is_string($configOrSessionId) ? $configOrSessionId : uniqid('ussd_', true);
            $this->config = $config;
        }

        $this->cacheKey = ($this->config['session_prefix'] ?? 'ussd_session_').$this->phoneNumber;
        $this->lastAccessTime = Carbon::now();

        // Default to the cache-backed store; an app can bind its own
        // SessionStoreInterface in the container to change where live session
        // state lives, without any change to this class's behaviour.
        $this->store = (function_exists('app') && app()->bound(SessionStoreInterface::class))
            ? app(SessionStoreInterface::class)
            : new CacheSessionStore;

        $this->loadOrInitializeSession();
    }

    /**
     * Set a session encryptor for encrypting/decrypting session data.
     */
    public function setEncryptor(SessionEncryptor $encryptor): void
    {
        $this->encryptor = $encryptor;
    }

    protected function loadOrInitializeSession(): void
    {
        $existingData = $this->store->get($this->cacheKey);

        if ($existingData !== null && $existingData !== []) {
            $this->data = $this->decryptData($existingData);
            $this->initializeEnhancedData();
            Log::debug('UnifiedUssdSession: Loaded existing session', [
                'phone' => $this->phoneNumber,
                'current_menu' => $this->data['current_menu'] ?? null,
                'step' => $this->data['step'] ?? 0,
                'status' => $this->getStatus(),
            ]);
        } else {
            $this->data = $this->getDefaultSessionData();
            $this->initializeEnhancedData();
            Log::debug('UnifiedUssdSession: Initialized new session', [
                'phone' => $this->phoneNumber,
            ]);
        }

        $this->data['last_access'] = Carbon::now();
        $this->updateAccessCount();
    }

    protected function getDefaultSessionData(): array
    {
        return [
            'current_menu' => null,
            'menu_history' => [],
            'form_data' => [],
            'menu_data' => [],
            'step' => 0,
            'user_data' => [],
            'created_at' => Carbon::now()->toIso8601String(),
            'updated_at' => Carbon::now()->toIso8601String(),
            'last_access' => Carbon::now()->toIso8601String(),
            'access_count' => 0,
            'session_flags' => [],

            'session_metadata' => [
                'version' => '2.0',
                'created_at' => Carbon::now()->toISOString(),
                'last_access' => Carbon::now()->toISOString(),
                'access_count' => 0,
                'status' => 'new',
            ],
            'continuation_data' => [],
            'recovery_context' => null,
            'user_preferences' => [],
            'interaction_history' => [],
            'performance_metrics' => [],
            'context_snapshots' => [],
        ];
    }

    protected function initializeEnhancedData(): void
    {
        $defaultEnhancedData = [
            'session_metadata' => [
                'version' => '2.0',
                'created_at' => Carbon::now()->toISOString(),
                'last_access' => Carbon::now()->toISOString(),
                'access_count' => 0,
                'status' => 'new',
            ],
            'continuation_data' => [],
            'recovery_context' => null,
            'user_preferences' => [],
            'interaction_history' => [],
            'performance_metrics' => [],
            'context_snapshots' => [],
        ];

        foreach ($defaultEnhancedData as $key => $value) {
            if (! isset($this->data[$key])) {
                $this->data[$key] = $value;
            }
        }
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return data_get($this->data, $key, $default);
    }

    public function set(string $key, mixed $value): void
    {
        data_set($this->data, $key, $value);
        $this->data['updated_at'] = Carbon::now()->toIso8601String();
    }

    public function getCurrentMenu(): ?string
    {
        return $this->get('current_menu');
    }

    public function setCurrentMenu(string $menuName): void
    {
        $currentMenu = $this->getCurrentMenu();

        if ($currentMenu && $currentMenu !== $menuName) {
            $this->addToMenuHistory($currentMenu);
        }

        $this->set('current_menu', $menuName);

        if ($currentMenu !== $menuName) {
            $this->set('step', 0);
        }

        $this->addInteractionHistory('menu_change', [
            'from' => $currentMenu,
            'to' => $menuName,
        ]);

        Log::debug('UnifiedUssdSession: Menu changed', [
            'from' => $currentMenu,
            'to' => $menuName,
            'phone' => $this->phoneNumber,
        ]);
    }

    protected function addToMenuHistory(string $menuName): void
    {
        $history = $this->get('menu_history', []);

        $historyEntry = [
            'menu' => $menuName,
            'data' => $this->get('menu_data', []),
            'step' => $this->get('step', 0),
            'timestamp' => Carbon::now()->toISOString(),
        ];

        $history[] = $historyEntry;

        $maxHistorySize = (int) ($this->config['max_history_size'] ?? 10);
        if (count($history) > $maxHistorySize) {
            $history = array_slice($history, -$maxHistorySize);
        }

        $this->set('menu_history', $history);
    }

    public function getStep(): int
    {
        return (int) $this->get('step', 0);
    }

    public function nextStep(): int
    {
        $currentStep = $this->getStep();
        $nextStep = $currentStep + 1;

        $maxSteps = $this->config['max_steps'] ?? 50;
        if ($nextStep > $maxSteps) {
            Log::warning('UnifiedUssdSession: Maximum steps exceeded', [
                'phone' => $this->phoneNumber,
                'current_step' => $currentStep,
                'max_steps' => $maxSteps,
            ]);

            return $currentStep;
        }

        $this->set('step', $nextStep);
        $this->addInteractionHistory('step_increment', ['from' => $currentStep, 'to' => $nextStep]);

        return $nextStep;
    }

    public function setStep(int $step): void
    {
        $oldStep = $this->getStep();
        $step = max(0, $step);
        $this->set('step', $step);

        $this->addInteractionHistory('step_set', ['from' => $oldStep, 'to' => $step]);

        Log::debug('UnifiedUssdSession: Step set', [
            'old_step' => $oldStep,
            'new_step' => $step,
            'phone' => $this->phoneNumber,
        ]);
    }

    public function getFormData(?string $key = null, mixed $default = null): mixed
    {
        $formData = $this->get('form_data', []);

        if ($key === null) {
            return $formData;
        }

        return $formData[$key] ?? $default;
    }

    public function setFormData(string|int $key, mixed $value): void
    {
        $formData = $this->get('form_data', []);
        $oldValue = $formData[$key] ?? null;
        $formData[$key] = $value;

        $this->set('form_data', $formData);

        $this->addInteractionHistory('form_data_update', [
            'field' => $key,
            'old_value' => $oldValue,
            'new_value' => $value,
        ]);

        Log::debug('UnifiedUssdSession: Form data updated', [
            'key' => $key,
            'old_value' => $oldValue,
            'new_value' => $value,
            'phone' => $this->phoneNumber,
        ]);
    }

    public function clearFormData(?string $key = null): void
    {
        if ($key === null) {
            $this->set('form_data', []);
            $this->addInteractionHistory('form_data_clear_all');
        } else {
            $formData = $this->get('form_data', []);
            unset($formData[$key]);
            $this->set('form_data', $formData);
            $this->addInteractionHistory('form_data_clear_field', ['field' => $key]);
        }
    }

    public function getMenuData(?string $key = null, mixed $default = null): mixed
    {
        $menuData = $this->get('menu_data', []);

        if ($key === null) {
            return $menuData;
        }

        return $menuData[$key] ?? $default;
    }

    public function setMenuData(array $data): void
    {
        $this->set('menu_data', $data);
        $this->addInteractionHistory('menu_data_set', ['data_keys' => array_keys($data)]);
    }

    public function getUserData(?string $key = null, mixed $default = null): mixed
    {
        $userData = $this->get('user_data', []);

        if ($key === null) {
            return $userData;
        }

        return $userData[$key] ?? $default;
    }

    public function setUserData(string $key, mixed $value): void
    {
        $userData = $this->get('user_data', []);
        $userData[$key] = $value;
        $this->set('user_data', $userData);

        $this->addInteractionHistory('user_data_update', ['key' => $key]);
    }

    public function goBack(bool $useSnapshot = false): bool
    {
        if ($useSnapshot) {
            $snapshots = $this->getContextSnapshots();
            if ($snapshots !== []) {
                $latestSnapshot = end($snapshots);

                return $this->restoreFromSnapshot($latestSnapshot);
            }
        }

        $history = $this->get('menu_history', []);

        if (empty($history)) {
            Log::debug('UnifiedUssdSession: No history to go back to', [
                'phone' => $this->phoneNumber,
            ]);

            return false;
        }

        $previous = array_pop($history);
        $this->set('menu_history', $history);

        $this->set('current_menu', $previous['menu']);
        $this->set('menu_data', $previous['data'] ?? []);
        $this->set('step', $previous['step'] ?? 0);

        $this->addInteractionHistory('navigation_back', [
            'restored_menu' => $previous['menu'],
            'restored_step' => $previous['step'] ?? 0,
            'method' => $useSnapshot ? 'snapshot' : 'history',
        ]);

        Log::info('UnifiedUssdSession: Went back to previous menu', [
            'restored_menu' => $previous['menu'],
            'restored_step' => $previous['step'] ?? 0,
            'phone' => $this->phoneNumber,
        ]);

        return true;
    }

    public function canGoBack(): bool
    {
        $history = $this->get('menu_history', []);

        return ! empty($history);
    }

    public function reset(): void
    {
        $oldData = $this->data;
        $this->data = $this->getDefaultSessionData();

        $this->data['created_at'] = $oldData['created_at'] ?? Carbon::now();
        $this->data['access_count'] = $oldData['access_count'] ?? 0;

        $this->addInteractionHistory('session_reset', [
            'old_menu' => $oldData['current_menu'] ?? null,
        ]);

        Log::info('UnifiedUssdSession: Session reset', [
            'phone' => $this->phoneNumber,
            'old_menu' => $oldData['current_menu'] ?? null,
        ]);
    }

    public function loadFromData(array $sessionData): void
    {
        $this->data = $sessionData;
        $this->initializeEnhancedData();
        $this->updateAccessCount();
        $this->updateLastAccess();
    }

    public function getData(): array
    {
        return $this->data;
    }

    public function setStatus(string $status): void
    {
        $oldStatus = $this->status;
        $this->status = $status;
        $this->set('session_metadata.status', $status);

        $this->addInteractionHistory('status_change', [
            'from' => $oldStatus,
            'to' => $status,
        ]);

        $this->set('session_metadata.previous_status', $oldStatus);
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setFlag(string $key, mixed $value): void
    {
        $flags = $this->get('session_flags', []);
        $flags[$key] = $value;
        $this->set('session_flags', $flags);

        $this->addInteractionHistory('flag_set', ['key' => $key, 'value' => $value]);
    }

    public function getFlag(string $key, mixed $default = null): mixed
    {
        $flags = $this->get('session_flags', []);

        return $flags[$key] ?? $default;
    }

    public function hasFlag(string $key): bool
    {
        $flags = $this->get('session_flags', []);

        return isset($flags[$key]);
    }

    public function setRecoveryContext(array $context): void
    {
        $this->recoveryContext = $context;
        $this->set('recovery_context', $context);
        $this->setFlag('recovered_session', true);

        $this->addInteractionHistory('recovery_context_set', ['type' => $context['type'] ?? 'unknown']);
    }

    public function getRecoveryContext(): ?array
    {
        return $this->recoveryContext ?? $this->get('recovery_context');
    }

    public function isRecovered(): bool
    {
        return $this->getFlag('recovered_session', false);
    }

    public function isInGracePeriod(): bool
    {
        if ($this->getFlag('in_grace_period', false)) {
            return true;
        }

        return $this->status === 'grace_period';
    }

    public function getContinuationMessage(): ?string
    {
        if ($this->getFlag('session_resumed', false) && ! $this->getFlag('continuation_message_shown', false)) {
            $this->setFlag('continuation_message_shown', true);

            $context = $this->getRecoveryContext();
            if ($context) {
                return match ($context['type']) {
                    'form_recovery' => "Welcome back! You were filling out a form ({$context['completion_percentage']}% complete). Would you like to continue?",
                    'transaction_recovery' => 'Welcome back! You had a pending transaction. Would you like to continue?',
                    'navigation_recovery' => 'Welcome back! You can continue from where you left off in the menu.',
                    default => 'Welcome back! You can continue from where you left off.',
                };
            }

            if ($this->getFlag('context_preserved', false)) {
                return "Welcome back! We've preserved your previous session context.";
            }
        }

        return null;
    }

    public function getGracePeriodMessage(): ?string
    {
        if ($this->isInGracePeriod() && ! $this->getFlag('grace_message_shown', false)) {
            $this->setFlag('grace_message_shown', true);

            return 'Your session timed out but you can continue where you left off.';
        }

        return null;
    }

    public function setUserPreference(string $key, mixed $value): void
    {
        $preferences = $this->get('user_preferences', []);
        $preferences[$key] = $value;
        $this->set('user_preferences', $preferences);

        $this->addInteractionHistory('preference_set', ['key' => $key]);
    }

    public function getUserPreference(string $key, mixed $default = null): mixed
    {
        $preferences = $this->get('user_preferences', []);

        return $preferences[$key] ?? $default;
    }

    public function addInteractionHistory(string $action, array $data = []): void
    {
        $history = $this->get('interaction_history', []);
        $history[] = [
            'action' => $action,
            'data' => $data,
            'timestamp' => Carbon::now()->toISOString(),
            'menu' => $this->getCurrentMenu(),
            'step' => $this->getStep(),
        ];

        $maxHistory = (int) ($this->config['session']['max_history'] ?? $this->config['max_history'] ?? 50);
        if (count($history) > $maxHistory) {
            $history = array_slice($history, -$maxHistory);
        }

        $this->set('interaction_history', $history);
    }

    public function getInteractionHistory(?int $limit = null): array
    {
        $history = $this->get('interaction_history', []);

        if ($limit) {
            return array_slice($history, -$limit);
        }

        return $history;
    }

    public function createContextSnapshot(string $name): void
    {
        $snapshots = $this->get('context_snapshots', []);
        $snapshots[$name] = [
            'menu' => $this->getCurrentMenu(),
            'step' => $this->getStep(),
            'form_data' => $this->getFormData(),
            'menu_data' => $this->getMenuData(),
            'timestamp' => Carbon::now()->toISOString(),
        ];

        $maxSnapshots = (int) ($this->config['session']['max_snapshots'] ?? $this->config['max_snapshots'] ?? 10);
        if (count($snapshots) > $maxSnapshots) {
            $snapshots = array_slice($snapshots, -$maxSnapshots, null, true);
        }

        $this->set('context_snapshots', $snapshots);

        $this->addInteractionHistory('snapshot_created', ['name' => $name]);
    }

    public function restoreFromSnapshot(string $name): bool
    {
        $snapshots = $this->get('context_snapshots', []);

        if (! isset($snapshots[$name])) {
            return false;
        }

        $snapshot = $snapshots[$name];

        $this->setCurrentMenu($snapshot['menu']);
        $this->setStep($snapshot['step']);
        $this->set('form_data', $snapshot['form_data']);
        $this->setMenuData($snapshot['menu_data']);

        $this->addInteractionHistory('context_restored', [
            'snapshot_name' => $name,
            'snapshot_timestamp' => $snapshot['timestamp'],
        ]);

        return true;
    }

    public function getContextSnapshots(): array
    {
        return array_keys($this->get('context_snapshots', []));
    }

    public function trackPerformance(string $metric, mixed $value, array $context = []): void
    {
        $metrics = $this->get('performance_metrics', []);
        $metrics[] = [
            'metric' => $metric,
            'value' => $value,
            'context' => $context,
            'timestamp' => Carbon::now()->toISOString(),
        ];

        if (count($metrics) > 100) {
            $metrics = array_slice($metrics, -100);
        }

        $this->set('performance_metrics', $metrics);
    }

    public function getPerformanceMetrics(?string $metric = null): array
    {
        $metrics = $this->get('performance_metrics', []);

        if ($metric) {
            return array_filter($metrics, fn (array $m): bool => $m['metric'] === $metric);
        }

        return $metrics;
    }

    public function updateLastAccess(): void
    {
        $this->lastAccessTime = Carbon::now();
        $this->set('session_metadata.last_access', $this->lastAccessTime->toISOString());
    }

    protected function updateAccessCount(): void
    {
        $currentCount = $this->get('session_metadata.access_count', 0);
        $this->set('session_metadata.access_count', $currentCount + 1);
    }

    public function getSessionDuration(): float
    {
        $createdAt = Carbon::parse($this->get('session_metadata.created_at'));

        return Carbon::now()->diffInSeconds($createdAt);
    }

    public function getTimeSinceLastAccess(): float
    {
        if ($this->lastAccessTime instanceof Carbon) {
            return Carbon::now()->diffInSeconds($this->lastAccessTime);
        }

        $lastAccess = $this->get('session_metadata.last_access');
        if ($lastAccess) {
            return Carbon::now()->diffInSeconds(Carbon::parse($lastAccess));
        }

        return 0;
    }

    public function isStale(int $maxAge = 1800): bool
    {
        return $this->getTimeSinceLastAccess() > $maxAge;
    }

    public function shouldContinue(): bool
    {
        if ($this->isInGracePeriod()) {
            return true;
        }

        if ($this->isRecovered()) {
            $context = $this->getRecoveryContext();
            if ($context) {
                switch ($context['type']) {
                    case 'form_recovery':
                        return $context['completion_percentage'] > 25;
                    case 'transaction_recovery':
                        return true;
                    case 'navigation_recovery':
                        return count($context['menu_path']) > 2;
                }
            }
        }

        return false;
    }

    protected function isCompleted(): bool
    {
        if ($this->getFlag('completed', false)) {
            return true;
        }

        return (bool) $this->getFlag('session_complete', false);
    }

    public function exists(): bool
    {
        $exists = $this->store->has($this->cacheKey);

        if ($exists) {
            $cachedData = $this->store->get($this->cacheKey);
            if (! is_array($cachedData)) {
                Log::warning('UnifiedUssdSession: Invalid cached session data, cleaning up', [
                    'phone' => $this->phoneNumber,
                ]);
                $this->store->forget($this->cacheKey);

                return false;
            }
        }

        return $exists;
    }

    public function save(): void
    {
        try {
            $this->updateLastAccess();
            $this->updateAccessCount();

            $metadata = $this->get('session_metadata', []);
            $metadata['last_save'] = Carbon::now()->toISOString();
            $metadata['status'] = $this->status;
            $this->set('session_metadata', $metadata);

            $this->data['updated_at'] = Carbon::now()->toIso8601String();
            $this->data['last_access'] = Carbon::now();

            $timeout = (int) ($this->config['session']['timeout'] ?? $this->config['session_timeout'] ?? 300);
            $dataToSave = $this->encryptData($this->data);
            $this->store->put($this->cacheKey, $dataToSave, $timeout);

            $this->saveToUserSessionsTable();

            Log::debug('UnifiedUssdSession: Session saved', [
                'phone' => $this->phoneNumber,
                'menu' => $this->getCurrentMenu(),
                'step' => $this->getStep(),
                'status' => $this->getStatus(),
                'timeout' => $timeout,
            ]);

        } catch (Exception $e) {
            Log::error('UnifiedUssdSession: Failed to save session', [
                'phone' => $this->phoneNumber,
                'error' => $e->getMessage(),
            ]);
        }
    }

    protected function saveToUserSessionsTable(): void
    {
        try {
            $userJourney = $this->extractUserJourney();
            $totalInteractions = $this->get('session_metadata.access_count', 0);
            $startedAt = isset($this->data['created_at']) ? Carbon::parse($this->data['created_at']) : Carbon::now();

            $exists = DB::table('ussd_user_sessions')
                ->where('session_id', $this->sessionId)
                ->exists();

            $data = [
                'phone_number' => $this->phoneNumber,
                'current_menu' => $this->getCurrentMenu(),
                'session_data' => json_encode($this->data),
                'started_at' => $startedAt,
                'last_activity' => Carbon::now(),
                'total_interactions' => $totalInteractions,
                'user_journey' => json_encode($userJourney),
                'completed' => $this->isCompleted(),
                'updated_at' => Carbon::now(),
            ];

            if ($exists) {
                DB::table('ussd_user_sessions')
                    ->where('session_id', $this->sessionId)
                    ->update($data);
            } else {
                $data['session_id'] = $this->sessionId;
                $data['created_at'] = Carbon::now();
                DB::table('ussd_user_sessions')->insert($data);
            }
        } catch (Exception $e) {
            Log::error('Failed to save session to user_sessions table', [
                'error' => $e->getMessage(),
                'session_id' => $this->sessionId,
                'phone' => $this->phoneNumber,
            ]);
        }
    }

    protected function extractUserJourney(): array
    {
        $journey = [];

        $history = $this->getInteractionHistory();
        foreach ($history as $interaction) {
            $journey[] = [
                'action' => $interaction['action'],
                'menu' => $interaction['menu'] ?? null,
                'timestamp' => $interaction['timestamp'],
                'step' => $interaction['step'] ?? null,
            ];
        }

        if ($this->getCurrentMenu()) {
            $journey[] = [
                'action' => 'current_state',
                'menu' => $this->getCurrentMenu(),
                'timestamp' => Carbon::now()->toISOString(),
                'step' => $this->getStep(),
                'current' => true,
            ];
        }

        return $journey;
    }

    /**
     * Encrypt session data if an encryptor is set.
     */
    protected function encryptData(array $data): array
    {
        if ($this->encryptor instanceof SessionEncryptor) {
            return $this->encryptor->encrypt($data);
        }

        return $data;
    }

    /**
     * Decrypt session data if an encryptor is set.
     */
    protected function decryptData(array $data): array
    {
        if ($this->encryptor instanceof SessionEncryptor) {
            return $this->encryptor->decrypt($data);
        }

        return $data;
    }

    public function destroy(): void
    {
        $this->store->forget($this->cacheKey);

        $this->addInteractionHistory('session_destroyed');

        Log::info('UnifiedUssdSession: Session destroyed', [
            'phone' => $this->phoneNumber,
        ]);
    }

    public function getPhoneNumber(): string
    {
        return $this->phoneNumber;
    }

    public function getSessionId(): string
    {
        return $this->sessionId;
    }

    public function getSummary(): array
    {
        return [
            'phone_number' => $this->getPhoneNumber(),
            'session_id' => $this->getSessionId(),
            'status' => $this->getStatus(),
            'current_menu' => $this->getCurrentMenu(),
            'step' => $this->getStep(),
            'duration' => $this->getSessionDuration(),
            'time_since_last_access' => $this->getTimeSinceLastAccess(),
            'access_count' => $this->get('session_metadata.access_count', 0),
            'can_go_back' => $this->canGoBack(),
            'form_data_keys' => array_keys($this->getFormData() ?? []),
            'menu_data_keys' => array_keys($this->getMenuData() ?? []),
            'user_data_keys' => array_keys($this->getUserData() ?? []),
            'session_flags' => $this->get('session_flags', []),
            'is_recovered' => $this->isRecovered(),
            'is_in_grace_period' => $this->isInGracePeriod(),
            'should_continue' => $this->shouldContinue(),
            'available_snapshots' => count($this->getContextSnapshots()),
            'interaction_count' => count($this->getInteractionHistory()),
            'created_at' => $this->get('created_at'),
            'last_access' => $this->get('last_access'),
        ];
    }
}
