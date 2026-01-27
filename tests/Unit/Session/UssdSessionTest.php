<?php

declare(strict_types=1);

namespace Moffhub\Ussd\Tests\Unit\Session;

use Moffhub\Ussd\Tests\TestCase;
use Moffhub\Ussd\UssdSession;

class UssdSessionTest extends TestCase
{
    private UssdSession $session;

    protected function setUp(): void
    {
        parent::setUp();
        $this->session = new UssdSession('+254712345678', 'test_session_123');
    }

    // ==================== Session Initialization ====================

    public function test_session_initializes_with_phone_and_session_id(): void
    {
        $session = new UssdSession('+254712345678', 'session_abc');

        $this->assertEquals('+254712345678', $session->getPhoneNumber());
        $this->assertEquals('session_abc', $session->getSessionId());
    }

    public function test_session_initializes_with_new_status(): void
    {
        $session = new UssdSession('+254712345678', 'session_abc');

        $this->assertEquals('new', $session->getStatus());
    }

    public function test_session_initializes_with_zero_step(): void
    {
        $session = new UssdSession('+254712345678', 'session_abc');

        $this->assertEquals(0, $session->getStep());
    }

    public function test_session_initializes_with_empty_current_menu(): void
    {
        $session = new UssdSession('+254712345678', 'session_abc');

        $this->assertNull($session->getCurrentMenu());
    }

    // ==================== Session Status Management ====================

    public function test_set_and_get_status(): void
    {
        $this->session->setStatus('active');

        $this->assertEquals('active', $this->session->getStatus());
    }

    public function test_status_transition_from_new_to_active(): void
    {
        $this->assertEquals('new', $this->session->getStatus());

        $this->session->setStatus('active');

        $this->assertEquals('active', $this->session->getStatus());
    }

    public function test_status_transition_to_grace_period(): void
    {
        $this->session->setStatus('grace_period');

        $this->assertEquals('grace_period', $this->session->getStatus());
    }

    public function test_status_transition_to_recovered(): void
    {
        $this->session->setStatus('recovered');

        $this->assertEquals('recovered', $this->session->getStatus());
    }

    public function test_status_transition_to_expired(): void
    {
        $this->session->setStatus('expired');

        $this->assertEquals('expired', $this->session->getStatus());
    }

    // ==================== Menu Navigation ====================

    public function test_set_and_get_current_menu(): void
    {
        $this->session->setCurrentMenu('main');

        $this->assertEquals('main', $this->session->getCurrentMenu());
    }

    public function test_menu_change_updates_current_menu(): void
    {
        $this->session->setCurrentMenu('main');
        $this->session->setCurrentMenu('register');

        $this->assertEquals('register', $this->session->getCurrentMenu());
    }

    public function test_set_and_get_step(): void
    {
        $this->session->setStep(5);

        $this->assertEquals(5, $this->session->getStep());
    }

    public function test_next_step_increments_step(): void
    {
        $this->session->setStep(0);
        $newStep = $this->session->nextStep();

        $this->assertEquals(1, $newStep);
        $this->assertEquals(1, $this->session->getStep());
    }

    public function test_increment_step(): void
    {
        $this->session->setStep(0);
        $this->session->setStep(1);
        $this->session->setStep(2);

        $this->assertEquals(2, $this->session->getStep());
    }

    // ==================== Form Data Management ====================

    public function test_set_and_get_form_data(): void
    {
        $this->session->setFormData('name', 'John Doe');

        $this->assertEquals('John Doe', $this->session->getFormData('name'));
    }

    public function test_get_form_data_with_default(): void
    {
        $result = $this->session->getFormData('nonexistent', 'default_value');

        $this->assertEquals('default_value', $result);
    }

    public function test_get_form_data_returns_null_for_missing_key(): void
    {
        $result = $this->session->getFormData('missing_key');

        $this->assertNull($result);
    }

    public function test_set_multiple_form_data_fields(): void
    {
        $this->session->setFormData('name', 'John');
        $this->session->setFormData('email', 'john@example.com');
        $this->session->setFormData('age', 25);

        $this->assertEquals('John', $this->session->getFormData('name'));
        $this->assertEquals('john@example.com', $this->session->getFormData('email'));
        $this->assertEquals(25, $this->session->getFormData('age'));
    }

    public function test_update_existing_form_data(): void
    {
        $this->session->setFormData('name', 'John');
        $this->session->setFormData('name', 'Jane');

        $this->assertEquals('Jane', $this->session->getFormData('name'));
    }

    public function test_clear_form_data(): void
    {
        $this->session->setFormData('name', 'John');
        $this->session->setFormData('email', 'john@example.com');

        $this->session->clearFormData();

        $this->assertNull($this->session->getFormData('name'));
        $this->assertNull($this->session->getFormData('email'));
    }

    public function test_clear_single_form_field(): void
    {
        $this->session->setFormData('name', 'John');
        $this->session->setFormData('email', 'john@example.com');

        $this->session->clearFormData('name');

        $this->assertNull($this->session->getFormData('name'));
        $this->assertEquals('john@example.com', $this->session->getFormData('email'));
    }

    public function test_get_all_form_data_with_null_key(): void
    {
        $this->session->setFormData('field1', 'value1');
        $this->session->setFormData('field2', 'value2');

        $allData = $this->session->getFormData();

        $this->assertIsArray($allData);
        $this->assertArrayHasKey('field1', $allData);
        $this->assertArrayHasKey('field2', $allData);
    }

    // ==================== Menu Data Management ====================

    public function test_set_and_get_menu_data(): void
    {
        $this->session->setMenuData(['key' => 'value']);

        $menuData = $this->session->getMenuData();

        $this->assertIsArray($menuData);
        $this->assertArrayHasKey('key', $menuData);
        $this->assertEquals('value', $menuData['key']);
    }

    public function test_get_menu_data_with_key(): void
    {
        $this->session->setMenuData(['specific_key' => 'specific_value']);

        $value = $this->session->getMenuData('specific_key');

        $this->assertEquals('specific_value', $value);
    }

    // ==================== User Data Management ====================

    public function test_set_and_get_user_data(): void
    {
        $this->session->setUserData('user_id', 12345);

        $this->assertEquals(12345, $this->session->getUserData('user_id'));
    }

    public function test_get_user_data_with_default(): void
    {
        $result = $this->session->getUserData('missing', 'default');

        $this->assertEquals('default', $result);
    }

    // ==================== Session Flags ====================

    public function test_set_and_has_flag(): void
    {
        $this->session->setFlag('is_premium', true);

        $this->assertTrue($this->session->hasFlag('is_premium'));
    }

    public function test_has_flag_returns_false_for_unset_flag(): void
    {
        $this->assertFalse($this->session->hasFlag('nonexistent_flag'));
    }

    public function test_get_flag_value(): void
    {
        $this->session->setFlag('custom_flag', 'custom_value');

        $this->assertEquals('custom_value', $this->session->getFlag('custom_flag'));
    }

    public function test_get_flag_with_default(): void
    {
        $result = $this->session->getFlag('missing_flag', 'default_flag_value');

        $this->assertEquals('default_flag_value', $result);
    }

    // ==================== Session Reset ====================

    public function test_reset_clears_session_state(): void
    {
        $this->session->setCurrentMenu('register');
        $this->session->setStep(5);
        $this->session->setFormData('name', 'John');

        $this->session->reset();

        $this->assertNull($this->session->getCurrentMenu());
        $this->assertEquals(0, $this->session->getStep());
    }

    // ==================== Recovery Context ====================

    public function test_set_and_get_recovery_context(): void
    {
        $context = [
            'type' => 'form',
            'menu' => 'register',
            'completion_percentage' => 50,
        ];

        $this->session->setRecoveryContext($context);

        $recoveryContext = $this->session->getRecoveryContext();

        $this->assertNotNull($recoveryContext);
        $this->assertArrayHasKey('type', $recoveryContext);
        $this->assertArrayHasKey('menu', $recoveryContext);
        $this->assertArrayHasKey('completion_percentage', $recoveryContext);
        $this->assertEquals('form', $recoveryContext['type']);
        $this->assertEquals('register', $recoveryContext['menu']);
        $this->assertEquals(50, $recoveryContext['completion_percentage']);
    }

    public function test_is_recovered_checks_flag(): void
    {
        $this->session->setFlag('recovered_session', true);

        $this->assertTrue($this->session->isRecovered());
    }

    public function test_is_not_recovered_when_active(): void
    {
        $this->session->setStatus('active');

        $this->assertFalse($this->session->isRecovered());
    }

    // ==================== Data Serialization ====================

    public function test_get_data_returns_session_data(): void
    {
        $this->session->setCurrentMenu('main');
        $this->session->setStep(2);
        $this->session->setFormData('name', 'Test');

        $data = $this->session->getData();

        $this->assertArrayHasKey('current_menu', $data);
        $this->assertEquals('main', $data['current_menu']);
    }

    public function test_get_summary(): void
    {
        $this->session->setCurrentMenu('register');
        $this->session->setStep(3);
        $this->session->setFormData('name', 'John');

        $summary = $this->session->getSummary();

        $this->assertArrayHasKey('phone_number', $summary);
        $this->assertArrayHasKey('session_id', $summary);
        $this->assertArrayHasKey('current_menu', $summary);
        $this->assertArrayHasKey('step', $summary);
    }

    // ==================== Grace Period & Continuation ====================

    public function test_is_in_grace_period(): void
    {
        $this->session->setStatus('grace_period');

        $this->assertTrue($this->session->isInGracePeriod());
    }

    public function test_is_not_in_grace_period_when_active(): void
    {
        $this->session->setStatus('active');

        $this->assertFalse($this->session->isInGracePeriod());
    }

    public function test_should_continue_in_grace_period(): void
    {
        $this->session->setStatus('grace_period');
        $result = $this->session->shouldContinue();

        $this->assertTrue($result);
    }

    // ==================== Menu History ====================

    public function test_can_go_back_returns_false_initially(): void
    {
        $this->assertFalse($this->session->canGoBack());
    }

    public function test_go_back_returns_false_without_history(): void
    {
        $result = $this->session->goBack();

        $this->assertFalse($result);
    }

    // ==================== Data Access with Dot Notation ====================

    public function test_get_with_dot_notation(): void
    {
        $this->session->set('user.profile.name', 'John');

        $this->assertEquals('John', $this->session->get('user.profile.name'));
    }

    public function test_get_returns_default_for_missing_key(): void
    {
        $result = $this->session->get('missing.nested.key', 'default');

        $this->assertEquals('default', $result);
    }

    public function test_set_top_level_key(): void
    {
        $this->session->set('simple_key', 'simple_value');

        $this->assertEquals('simple_value', $this->session->get('simple_key'));
    }

    // ==================== Interaction History ====================

    public function test_get_interaction_history_initially_empty(): void
    {
        $history = $this->session->getInteractionHistory();

        $this->assertEmpty($history);
    }

    public function test_add_interaction_history(): void
    {
        $this->session->addInteractionHistory('menu_change', ['from' => 'main', 'to' => 'register']);

        $history = $this->session->getInteractionHistory();

        $this->assertNotEmpty($history);
    }

    // ==================== User Preferences ====================

    public function test_set_and_get_user_preference(): void
    {
        $this->session->setUserPreference('language', 'en');

        $this->assertEquals('en', $this->session->getUserPreference('language'));
    }

    public function test_get_user_preference_with_default(): void
    {
        $result = $this->session->getUserPreference('missing_pref', 'default_pref');

        $this->assertEquals('default_pref', $result);
    }

    // ==================== Context Snapshots ====================

    public function test_create_context_snapshot(): void
    {
        $this->session->setCurrentMenu('register');
        $this->session->setFormData('name', 'John');

        $this->session->createContextSnapshot('before_payment');

        $snapshots = $this->session->getContextSnapshots();

        $this->assertNotEmpty($snapshots);
        $this->assertContains('before_payment', $snapshots);
    }

    public function test_restore_from_snapshot(): void
    {
        $this->session->setCurrentMenu('register');
        $this->session->setFormData('name', 'John');

        $this->session->createContextSnapshot('saved_state');

        $this->session->setCurrentMenu('payment');
        $this->session->setFormData('name', 'Jane');

        $result = $this->session->restoreFromSnapshot('saved_state');

        $this->assertTrue($result);
        $this->assertEquals('register', $this->session->getCurrentMenu());
    }

    public function test_restore_from_nonexistent_snapshot_returns_false(): void
    {
        $result = $this->session->restoreFromSnapshot('nonexistent');

        $this->assertFalse($result);
    }

    // ==================== Performance Tracking ====================

    public function test_track_performance(): void
    {
        $this->session->trackPerformance('response_time', 150, ['menu' => 'main']);

        $metrics = $this->session->getPerformanceMetrics();

        $this->assertNotEmpty($metrics);
    }

    public function test_get_performance_metrics_by_type(): void
    {
        $this->session->trackPerformance('response_time', 100);
        $this->session->trackPerformance('memory_usage', 1024);

        $responseTimeMetrics = $this->session->getPerformanceMetrics('response_time');

        $this->assertNotEmpty($responseTimeMetrics);
    }

    // ==================== Session Duration ====================

    public function test_get_session_duration(): void
    {
        $duration = $this->session->getSessionDuration();

        // Duration should be close to 0 for a newly created session (allowing for timing precision)
        $this->assertLessThan(1.0, abs($duration));
    }

    public function test_get_time_since_last_access(): void
    {
        $this->session->updateLastAccess();
        $timeSince = $this->session->getTimeSinceLastAccess();

        // Time since can be very small or slightly negative due to timing
        $this->assertLessThan(1.0, abs($timeSince));
    }

    public function test_is_stale_for_new_session(): void
    {
        $isStale = $this->session->isStale(1800);

        $this->assertFalse($isStale);
    }

    // ==================== Session Existence ====================

    public function test_exists_checks_cache(): void
    {
        // A newly created session that hasn't been saved to cache won't exist in cache
        $exists = $this->session->exists();

        // The exists method checks cache, which may or may not have the session
        // This test verifies the method returns a deterministic result
        $this->assertFalse($exists);
    }

    // ==================== Edge Cases ====================

    public function test_form_data_with_array_value(): void
    {
        $this->session->setFormData('selected_items', [1, 2, 3]);

        $items = $this->session->getFormData('selected_items');

        $this->assertCount(3, $items);
        $this->assertEquals([1, 2, 3], $items);
    }

    public function test_form_data_with_nested_array(): void
    {
        $nestedData = [
            'level1' => [
                'level2' => 'deep_value',
            ],
        ];

        $this->session->setFormData('nested', $nestedData);

        $result = $this->session->getFormData('nested');

        $this->assertEquals('deep_value', $result['level1']['level2']);
    }

    public function test_form_data_with_null_value(): void
    {
        $this->session->setFormData('nullable_field', null);

        $this->assertNull($this->session->getFormData('nullable_field'));
    }

    public function test_form_data_with_empty_string(): void
    {
        $this->session->setFormData('empty_field', '');

        $this->assertEquals('', $this->session->getFormData('empty_field'));
    }

    public function test_form_data_with_zero_value(): void
    {
        $this->session->setFormData('zero_field', 0);

        $this->assertEquals(0, $this->session->getFormData('zero_field'));
    }

    public function test_form_data_with_boolean_false(): void
    {
        $this->session->setFormData('bool_field', false);

        $this->assertFalse($this->session->getFormData('bool_field'));
    }

    public function test_session_with_special_characters_in_phone(): void
    {
        $session = new UssdSession('+254-712-345678', 'session_special');

        $this->assertEquals('+254-712-345678', $session->getPhoneNumber());
    }

    public function test_session_id_with_special_characters(): void
    {
        $session = new UssdSession('+254712345678', 'session_123_abc-xyz');

        $this->assertEquals('session_123_abc-xyz', $session->getSessionId());
    }

    public function test_form_data_with_numeric_string_key(): void
    {
        $this->session->setFormData('1', 'numeric_key_value');

        $this->assertEquals('numeric_key_value', $this->session->getFormData('1'));
    }

    public function test_load_from_data(): void
    {
        $sessionData = [
            'current_menu' => 'loaded_menu',
            'step' => 5,
            'form_data' => ['field1' => 'value1'],
        ];

        $this->session->loadFromData($sessionData);

        $this->assertEquals('loaded_menu', $this->session->getCurrentMenu());
        $this->assertEquals(5, $this->session->getStep());
    }
}
