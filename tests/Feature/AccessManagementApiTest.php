<?php

declare(strict_types=1);

namespace Moffhub\Ussd\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Moffhub\Ussd\Models\UssdAccessList;
use Moffhub\Ussd\Tests\TestCase;

class AccessManagementApiTest extends TestCase
{
    use RefreshDatabase;

    protected function getEnvironmentSetUp($app): void
    {
        parent::getEnvironmentSetUp($app);

        // Enable admin API without auth middleware for testing
        $app['config']->set('ussd.admin.enabled', true);
        $app['config']->set('ussd.admin.prefix', 'ussd/admin');
        $app['config']->set('ussd.admin.middleware', ['api']);

        // Enable database for access list tests
        $app['config']->set('ussd.database.enabled', true);
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->loadMigrationsFrom(__DIR__.'/../../database/migrations');
    }

    // ==================== Access List CRUD ====================

    public function test_list_whitelist_entries(): void
    {
        UssdAccessList::addToWhitelist('+254712345678', 'VIP', 'api');
        UssdAccessList::addToWhitelist('+254712345679', 'Staff', 'api');

        $response = $this->getJson('/ussd/admin/access-list?type=whitelist');

        $response->assertOk();
        $response->assertJsonCount(2, 'data');
    }

    public function test_list_blacklist_entries(): void
    {
        UssdAccessList::addToBlacklist('+254712345678', 'Fraud', 'api');

        $response = $this->getJson('/ussd/admin/access-list?type=blacklist');

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
    }

    public function test_list_all_entries_without_type_filter(): void
    {
        UssdAccessList::addToWhitelist('+254712345678', 'VIP', 'api');
        UssdAccessList::addToBlacklist('+254712345679', 'Fraud', 'api');

        $response = $this->getJson('/ussd/admin/access-list');

        $response->assertOk();
        $response->assertJsonCount(2, 'data');
    }

    public function test_list_entries_validates_type(): void
    {
        $response = $this->getJson('/ussd/admin/access-list?type=invalid');

        $response->assertStatus(422);
    }

    public function test_add_phone_to_whitelist(): void
    {
        $response = $this->postJson('/ussd/admin/access-list', [
            'type' => 'whitelist',
            'phones' => ['+254712345678'],
            'reason' => 'VIP customer',
        ]);

        $response->assertStatus(201);
        $response->assertJsonFragment(['message' => '1 phone(s) added to whitelist']);

        $this->assertDatabaseHas('ussd_access_lists', [
            'phone_number' => '+254712345678',
            'type' => 'whitelist',
            'reason' => 'VIP customer',
        ]);
    }

    public function test_add_phone_to_blacklist(): void
    {
        $response = $this->postJson('/ussd/admin/access-list', [
            'type' => 'blacklist',
            'phones' => ['+254712345678'],
            'reason' => 'Suspicious activity',
        ]);

        $response->assertStatus(201);

        $this->assertDatabaseHas('ussd_access_lists', [
            'phone_number' => '+254712345678',
            'type' => 'blacklist',
        ]);
    }

    public function test_add_multiple_phones(): void
    {
        $response = $this->postJson('/ussd/admin/access-list', [
            'type' => 'whitelist',
            'phones' => ['+254712345678', '+254712345679', '+254712345680'],
            'reason' => 'Batch add',
        ]);

        $response->assertStatus(201);
        $response->assertJsonFragment(['message' => '3 phone(s) added to whitelist']);
    }

    public function test_add_phone_validates_required_fields(): void
    {
        $response = $this->postJson('/ussd/admin/access-list', []);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['type', 'phones']);
    }

    public function test_add_phone_validates_type(): void
    {
        $response = $this->postJson('/ussd/admin/access-list', [
            'type' => 'invalid',
            'phones' => ['+254712345678'],
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['type']);
    }

    public function test_remove_entry(): void
    {
        $entry = UssdAccessList::addToWhitelist('+254712345678', 'VIP', 'api');

        $response = $this->deleteJson('/ussd/admin/access-list/'.$entry->id);

        $response->assertOk();
        $response->assertJsonFragment(['message' => 'Entry deactivated successfully']);

        $this->assertDatabaseHas('ussd_access_lists', [
            'id' => $entry->id,
            'is_active' => false,
        ]);
    }

    public function test_remove_nonexistent_entry(): void
    {
        $response = $this->deleteJson('/ussd/admin/access-list/999');

        $response->assertNotFound();
    }

    // ==================== Bulk Operations ====================

    public function test_bulk_add_phones(): void
    {
        $response = $this->postJson('/ussd/admin/access-list/bulk', [
            'type' => 'blacklist',
            'phones' => ['+254712345678', '+254712345679', '+254712345680'],
            'reason' => 'Bulk block',
        ]);

        $response->assertStatus(201);
        $response->assertJsonFragment(['message' => '3 phone(s) added to blacklist']);

        $this->assertDatabaseCount('ussd_access_lists', 3);
    }

    public function test_bulk_add_validates_input(): void
    {
        $response = $this->postJson('/ussd/admin/access-list/bulk', [
            'type' => 'invalid',
        ]);

        $response->assertStatus(422);
    }

    public function test_bulk_remove_entries(): void
    {
        $entry1 = UssdAccessList::addToWhitelist('+254712345678', 'VIP', 'api');
        $entry2 = UssdAccessList::addToWhitelist('+254712345679', 'VIP', 'api');
        $entry3 = UssdAccessList::addToWhitelist('+254712345680', 'VIP', 'api');

        $response = $this->deleteJson('/ussd/admin/access-list/bulk', [
            'ids' => [$entry1->id, $entry2->id],
        ]);

        $response->assertOk();
        $response->assertJsonFragment(['message' => '2 entry/entries deactivated successfully']);

        $this->assertDatabaseHas('ussd_access_lists', [
            'id' => $entry1->id,
            'is_active' => false,
        ]);
        $this->assertDatabaseHas('ussd_access_lists', [
            'id' => $entry3->id,
            'is_active' => true,
        ]);
    }

    public function test_bulk_remove_validates_ids(): void
    {
        $response = $this->deleteJson('/ussd/admin/access-list/bulk', []);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['ids']);
    }

    // ==================== Export ====================

    public function test_export_as_json(): void
    {
        UssdAccessList::addToWhitelist('+254712345678', 'VIP', 'api');
        UssdAccessList::addToWhitelist('+254712345679', 'Staff', 'api');

        $response = $this->getJson('/ussd/admin/access-list/export?type=whitelist&format=json');

        $response->assertOk();
        $response->assertJsonCount(2, 'data');
    }

    public function test_export_as_csv(): void
    {
        UssdAccessList::addToWhitelist('+254712345678', 'VIP', 'api');

        $response = $this->get('/ussd/admin/access-list/export?type=whitelist&format=csv');

        $response->assertOk();
        $this->assertStringStartsWith('text/csv', (string) $response->headers->get('content-type'));
    }

    public function test_export_validates_type(): void
    {
        $response = $this->getJson('/ussd/admin/access-list/export?format=json');

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['type']);
    }

    public function test_export_validates_format(): void
    {
        $response = $this->getJson('/ussd/admin/access-list/export?type=whitelist&format=xml');

        $response->assertStatus(422);
    }

    // ==================== Rate Limit Overrides ====================

    public function test_get_rate_limits_config(): void
    {
        $response = $this->getJson('/ussd/admin/rate-limits');

        $response->assertOk();
        $response->assertJsonStructure([
            'config' => ['max_requests_per_minute', 'max_requests_per_hour', 'max_requests_per_day'],
            'active_blocks',
        ]);
    }

    public function test_set_rate_limit_override(): void
    {
        $response = $this->postJson('/ussd/admin/rate-limits/override', [
            'phone' => '+254712345678',
            'per_minute' => 20,
            'per_hour' => 200,
            'expires_at' => now()->addMonth()->toDateTimeString(),
        ]);

        $response->assertStatus(201);
        $response->assertJsonFragment(['message' => 'Rate limit override set for +254712345678']);

        $this->assertTrue(Cache::has('ussd_rate_override_+254712345678'));
    }

    public function test_set_rate_limit_override_validates_phone(): void
    {
        $response = $this->postJson('/ussd/admin/rate-limits/override', [
            'per_minute' => 20,
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['phone']);
    }

    public function test_remove_rate_limit_override(): void
    {
        Cache::put('ussd_rate_override_+254712345678', ['per_minute' => 20], 3600);

        $response = $this->deleteJson('/ussd/admin/rate-limits/override/+254712345678');

        $response->assertOk();
        $response->assertJsonFragment(['message' => 'Rate limit override removed for +254712345678']);
        $this->assertFalse(Cache::has('ussd_rate_override_+254712345678'));
    }

    public function test_remove_nonexistent_rate_limit_override(): void
    {
        $response = $this->deleteJson('/ussd/admin/rate-limits/override/+254700000000');

        $response->assertNotFound();
    }

    // ==================== Sessions ====================

    public function test_list_sessions(): void
    {
        DB::table('ussd_sessions')->insert([
            'phone_number' => '+254712345678',
            'session_id' => 'sess_1',
            'session_data' => '{}',
            'status' => 'active',
            'step' => 1,
            'access_count' => 1,
            'expires_at' => now()->addMinutes(5),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->getJson('/ussd/admin/sessions');

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
    }

    public function test_list_sessions_filtered_by_status(): void
    {
        DB::table('ussd_sessions')->insert([
            'phone_number' => '+254712345678',
            'session_id' => 'sess_active',
            'session_data' => '{}',
            'status' => 'active',
            'step' => 1,
            'access_count' => 1,
            'expires_at' => now()->addMinutes(5),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('ussd_sessions')->insert([
            'phone_number' => '+254712345679',
            'session_id' => 'sess_expired',
            'session_data' => '{}',
            'status' => 'expired',
            'step' => 3,
            'access_count' => 5,
            'expires_at' => now()->subMinutes(5),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->getJson('/ussd/admin/sessions?status=active');

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
    }

    public function test_list_sessions_filtered_by_phone(): void
    {
        DB::table('ussd_sessions')->insert([
            'phone_number' => '+254712345678',
            'session_id' => 'sess_phone1',
            'session_data' => '{}',
            'status' => 'active',
            'step' => 1,
            'access_count' => 1,
            'expires_at' => now()->addMinutes(5),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('ussd_sessions')->insert([
            'phone_number' => '+254712345679',
            'session_id' => 'sess_phone2',
            'session_data' => '{}',
            'status' => 'active',
            'step' => 1,
            'access_count' => 1,
            'expires_at' => now()->addMinutes(5),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->getJson('/ussd/admin/sessions?phone='.urlencode('+254712345678'));

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
    }

    public function test_list_sessions_validates_status(): void
    {
        $response = $this->getJson('/ussd/admin/sessions?status=invalid');

        $response->assertStatus(422);
    }
}
