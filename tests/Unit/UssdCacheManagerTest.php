<?php

declare(strict_types=1);

namespace Moffhub\Ussd\Tests\Unit;

use Illuminate\Support\Facades\Cache;
use Moffhub\Ussd\Cache\UssdCacheManager;
use Moffhub\Ussd\Tests\TestCase;

class UssdCacheManagerTest extends TestCase
{
    private UssdCacheManager $cacheManager;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        $this->cacheManager = new UssdCacheManager([
            'prefix' => 'test_ussd_cache_',
            'default_ttl' => 300,
            'menu_content_ttl' => 3600,
            'data_provider_ttl' => 600,
            'user_data_ttl' => 1800,
            'enabled' => true,
        ]);
    }

    // ==================== Menu caching - store ====================

    public function test_cache_menu_content_stores_data(): void
    {
        $result = $this->cacheManager->cacheMenuContent('main_menu', ['html' => '<div>Menu</div>']);

        $this->assertTrue($result);
    }

    public function test_cache_menu_content_with_user_id(): void
    {
        $result = $this->cacheManager->cacheMenuContent('main_menu', 'User Menu', 'user_123');

        $this->assertTrue($result);
    }

    public function test_cache_menu_content_with_custom_ttl(): void
    {
        $result = $this->cacheManager->cacheMenuContent('main_menu', 'content', null, 60);

        $this->assertTrue($result);
    }

    // ==================== Menu caching - retrieve ====================

    public function test_get_menu_content_retrieves_cached_data(): void
    {
        $this->cacheManager->cacheMenuContent('test_menu', 'Hello World');

        $result = $this->cacheManager->getMenuContent('test_menu');

        $this->assertEquals('Hello World', $result);
    }

    public function test_get_menu_content_returns_null_for_missing(): void
    {
        $result = $this->cacheManager->getMenuContent('nonexistent_menu');

        $this->assertNull($result);
    }

    public function test_get_menu_content_with_user_id(): void
    {
        $this->cacheManager->cacheMenuContent('user_menu', 'User Content', 'user_123');

        $result = $this->cacheManager->getMenuContent('user_menu', 'user_123');

        $this->assertEquals('User Content', $result);
    }

    public function test_get_menu_content_different_user_returns_null(): void
    {
        $this->cacheManager->cacheMenuContent('user_menu', 'User Content', 'user_123');

        $result = $this->cacheManager->getMenuContent('user_menu', 'user_456');

        $this->assertNull($result);
    }

    // ==================== User data caching ====================

    public function test_cache_user_data_stores_data(): void
    {
        $result = $this->cacheManager->cacheUserData('user_123', 'preferences', ['lang' => 'en']);

        $this->assertTrue($result);
    }

    public function test_get_user_data_retrieves_cached_data(): void
    {
        $this->cacheManager->cacheUserData('user_123', 'preferences', ['lang' => 'en']);

        $result = $this->cacheManager->getUserData('user_123', 'preferences');

        $this->assertEquals(['lang' => 'en'], $result);
    }

    public function test_get_user_data_returns_null_for_missing(): void
    {
        $result = $this->cacheManager->getUserData('user_999', 'nonexistent');

        $this->assertNull($result);
    }

    // ==================== Invalidation ====================

    public function test_invalidate_menu_clears_cache(): void
    {
        $this->cacheManager->cacheMenuContent('test_menu', 'content');

        $result = $this->cacheManager->invalidate('menu', 'test_menu');

        $this->assertTrue($result);
    }

    public function test_invalidate_all_clears_everything(): void
    {
        $this->cacheManager->cacheMenuContent('menu1', 'content1');
        $this->cacheManager->cacheUserData('user1', 'key1', 'data1');

        $result = $this->cacheManager->invalidate('all');

        $this->assertTrue($result);
    }

    public function test_invalidate_user_cache(): void
    {
        $this->cacheManager->cacheUserData('user_123', 'key', 'data');

        $result = $this->cacheManager->invalidate('user', 'user_123');

        $this->assertTrue($result);
    }

    public function test_invalidate_without_identifier_returns_false(): void
    {
        $result = $this->cacheManager->invalidate('menu');

        $this->assertFalse($result);
    }

    // ==================== TTL configuration ====================

    public function test_custom_ttl_respected(): void
    {
        $manager = new UssdCacheManager([
            'prefix' => 'test_',
            'default_ttl' => 60,
            'menu_content_ttl' => 120,
            'enabled' => true,
        ]);

        $result = $manager->cacheMenuContent('test', 'data');

        $this->assertTrue($result);
    }

    // ==================== Cache key generation ====================

    public function test_menu_content_key_includes_prefix(): void
    {
        $reflection = new \ReflectionClass($this->cacheManager);
        $method = $reflection->getMethod('getMenuContentKey');
        $method->setAccessible(true);

        $key = $method->invoke($this->cacheManager, 'main_menu');

        $this->assertStringStartsWith('test_ussd_cache_', $key);
        $this->assertStringContainsString('main_menu', $key);
    }

    public function test_menu_content_key_includes_user_id(): void
    {
        $reflection = new \ReflectionClass($this->cacheManager);
        $method = $reflection->getMethod('getMenuContentKey');
        $method->setAccessible(true);

        $key = $method->invoke($this->cacheManager, 'main_menu', 'user_123');

        $this->assertStringContainsString('user_123', $key);
    }

    public function test_data_provider_key_includes_filter_hash(): void
    {
        $reflection = new \ReflectionClass($this->cacheManager);
        $method = $reflection->getMethod('getDataProviderKey');
        $method->setAccessible(true);

        $key1 = $method->invoke($this->cacheManager, 'provider', ['filter1' => 'a']);
        $key2 = $method->invoke($this->cacheManager, 'provider', ['filter1' => 'b']);

        $this->assertNotEquals($key1, $key2);
    }

    public function test_user_data_key_generation(): void
    {
        $reflection = new \ReflectionClass($this->cacheManager);
        $method = $reflection->getMethod('getUserDataKey');
        $method->setAccessible(true);

        $key = $method->invoke($this->cacheManager, 'user_123', 'preferences');

        $this->assertStringContainsString('user_123', $key);
        $this->assertStringContainsString('preferences', $key);
    }

    // ==================== Disabled cache ====================

    public function test_disabled_cache_store_returns_false(): void
    {
        $manager = new UssdCacheManager(['enabled' => false]);

        $this->assertFalse($manager->cacheMenuContent('test', 'data'));
        $this->assertFalse($manager->cacheUserData('user', 'key', 'data'));
    }

    public function test_disabled_cache_retrieve_returns_null(): void
    {
        $manager = new UssdCacheManager(['enabled' => false]);

        $this->assertNull($manager->getMenuContent('test'));
        $this->assertNull($manager->getUserData('user', 'key'));
        $this->assertNull($manager->getDataProvider('provider'));
    }

    public function test_disabled_cache_invalidate_returns_false(): void
    {
        $manager = new UssdCacheManager(['enabled' => false]);

        $this->assertFalse($manager->invalidate('all'));
    }

    // ==================== Stats ====================

    public function test_get_stats_returns_config_info(): void
    {
        $stats = $this->cacheManager->getStats();

        $this->assertTrue($stats['enabled']);
        $this->assertStringStartsWith('test_ussd_cache_', $stats['prefix']);
        $this->assertEquals(300, $stats['default_ttl']);
    }

    public function test_get_stats_disabled_returns_minimal(): void
    {
        $manager = new UssdCacheManager(['enabled' => false]);
        $stats = $manager->getStats();

        $this->assertFalse($stats['enabled']);
    }

    // ==================== Warm-up ====================

    public function test_warm_up_returns_true_when_enabled(): void
    {
        $result = $this->cacheManager->warmUp([]);

        $this->assertTrue($result);
    }

    public function test_warm_up_returns_false_when_disabled(): void
    {
        $manager = new UssdCacheManager(['enabled' => false]);

        $result = $manager->warmUp([]);

        $this->assertFalse($result);
    }
}
