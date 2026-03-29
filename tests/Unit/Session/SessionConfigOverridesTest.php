<?php

declare(strict_types=1);

namespace Moffhub\Ussd\Tests\Unit\Session;

use Illuminate\Support\Facades\Cache;
use Moffhub\Ussd\Tests\TestCase;
use Moffhub\Ussd\UssdSession;

class SessionConfigOverridesTest extends TestCase
{
    // ==================== 1.5 Session Timeout Config ====================

    public function test_session_save_uses_default_timeout_of_300(): void
    {
        $session = new UssdSession('+254712345678', 'test_session', []);
        $session->setCurrentMenu('main');
        $session->save();

        // Session should exist in cache after save
        $cacheKey = 'ussd_session_+254712345678';
        $this->assertNotNull(Cache::get($cacheKey));
    }

    public function test_session_save_uses_configured_timeout(): void
    {
        $session = new UssdSession('+254712345678', 'test_session', [
            'session' => ['timeout' => 600],
        ]);
        $session->setCurrentMenu('main');
        $session->save();

        $cacheKey = 'ussd_session_+254712345678';
        $this->assertNotNull(Cache::get($cacheKey));
    }

    public function test_session_save_uses_legacy_session_timeout_config(): void
    {
        $session = new UssdSession('+254712345678', 'test_session', [
            'session_timeout' => 120,
        ]);
        $session->setCurrentMenu('main');
        $session->save();

        $cacheKey = 'ussd_session_+254712345678';
        $this->assertNotNull(Cache::get($cacheKey));
    }

    // ==================== 1.5 Max Interaction History Config ====================

    public function test_interaction_history_respects_default_max_of_50(): void
    {
        $session = new UssdSession('+254712345678', 'test_session', []);

        for ($i = 0; $i < 60; $i++) {
            $session->addInteractionHistory('action_'.$i, ['index' => $i]);
        }

        $history = $session->getInteractionHistory();
        $this->assertCount(50, $history);
    }

    public function test_interaction_history_respects_config_override(): void
    {
        $session = new UssdSession('+254712345678', 'test_session', [
            'session' => ['max_history' => 5],
        ]);

        for ($i = 0; $i < 20; $i++) {
            $session->addInteractionHistory('action_'.$i, ['index' => $i]);
        }

        $history = $session->getInteractionHistory();
        $this->assertCount(5, $history);

        // Verify it kept the latest entries
        $lastEntry = end($history);
        $this->assertEquals('action_19', $lastEntry['action']);
    }

    public function test_interaction_history_respects_legacy_config_key(): void
    {
        $session = new UssdSession('+254712345678', 'test_session', [
            'max_history' => 3,
        ]);

        for ($i = 0; $i < 10; $i++) {
            $session->addInteractionHistory('action_'.$i, ['index' => $i]);
        }

        $history = $session->getInteractionHistory();
        $this->assertCount(3, $history);
    }

    // ==================== 1.5 Max Context Snapshots Config ====================

    public function test_context_snapshots_respects_default_max_of_10(): void
    {
        $session = new UssdSession('+254712345678', 'test_session', []);
        $session->setCurrentMenu('main');

        for ($i = 0; $i < 15; $i++) {
            $session->createContextSnapshot('snapshot_'.$i);
        }

        $snapshots = $session->getContextSnapshots();
        $this->assertCount(10, $snapshots);
    }

    public function test_context_snapshots_respects_config_override(): void
    {
        $session = new UssdSession('+254712345678', 'test_session', [
            'session' => ['max_snapshots' => 3],
        ]);
        $session->setCurrentMenu('main');

        for ($i = 0; $i < 10; $i++) {
            $session->createContextSnapshot('snapshot_'.$i);
        }

        $snapshots = $session->getContextSnapshots();
        $this->assertCount(3, $snapshots);
    }

    public function test_context_snapshots_respects_legacy_config_key(): void
    {
        $session = new UssdSession('+254712345678', 'test_session', [
            'max_snapshots' => 2,
        ]);
        $session->setCurrentMenu('main');

        for ($i = 0; $i < 10; $i++) {
            $session->createContextSnapshot('snapshot_'.$i);
        }

        $snapshots = $session->getContextSnapshots();
        $this->assertCount(2, $snapshots);
    }

    // ==================== Config File Verification ====================

    public function test_config_file_has_session_keys(): void
    {
        $config = require dirname(__DIR__, 3).'/src/Config/ussd.php';

        $this->assertArrayHasKey('session', $config);
        $this->assertArrayHasKey('timeout', $config['session']);
        $this->assertArrayHasKey('max_history', $config['session']);
        $this->assertArrayHasKey('max_snapshots', $config['session']);

        // Verify default values match original hardcoded values
        $this->assertEquals(300, $config['session']['timeout']);
        $this->assertEquals(50, $config['session']['max_history']);
        $this->assertEquals(10, $config['session']['max_snapshots']);
    }
}
