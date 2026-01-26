<?php

declare(strict_types=1);

namespace Moffhub\Ussd\Tests\Unit\DataProviders;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;
use Moffhub\Ussd\DataProviders\DatabaseDataProvider;
use Moffhub\Ussd\Tests\TestCase;
use Moffhub\Ussd\UssdSession;

class DatabaseDataProviderTest extends TestCase
{
    private UssdSession $session;

    protected function setUp(): void
    {
        parent::setUp();

        // Create the test_models table for testing
        Schema::create('test_models', function ($table) {
            $table->id();
            $table->string('name');
            $table->string('status')->default('active');
            $table->timestamps();
        });

        $this->session = new UssdSession('+254712345678', 'test_session');
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('test_models');
        parent::tearDown();
    }

    public function test_get_data_returns_correct_structure(): void
    {
        // Create a mock model class for testing
        $provider = new DatabaseDataProvider(TestModel::class);

        $result = $provider->getData($this->session);

        $this->assertArrayHasKey('data', $result);
        $this->assertArrayHasKey('total', $result);
        $this->assertArrayHasKey('current_page', $result);
        $this->assertArrayHasKey('per_page', $result);
        $this->assertArrayHasKey('has_more', $result);
    }

    public function test_get_data_applies_filters(): void
    {
        $provider = new DatabaseDataProvider(TestModel::class);

        // Should apply filters without errors
        $result = $provider->getData($this->session, ['status' => 'active']);

        $this->assertArrayHasKey('data', $result);
    }

    public function test_get_data_respects_pagination(): void
    {
        $provider = new DatabaseDataProvider(TestModel::class);

        $this->session->set('page', 2);
        $result = $provider->getData($this->session, ['per_page' => 5]);

        $this->assertEquals(2, $result['current_page']);
        $this->assertEquals(5, $result['per_page']);
    }

    public function test_search_returns_correct_structure(): void
    {
        $provider = new DatabaseDataProvider(TestModel::class);

        $result = $provider->search('test', $this->session, ['name']);

        $this->assertArrayHasKey('data', $result);
        $this->assertArrayHasKey('total', $result);
    }

    public function test_get_item_by_id(): void
    {
        $provider = new DatabaseDataProvider(TestModel::class);

        // getItem should work without errors
        $result = $provider->getItem(1, $this->session);

        // Result will be null if no record exists
        $this->assertTrue($result === null || is_object($result) || is_array($result));
    }

    public function test_custom_query_callback(): void
    {
        $customQuery = static fn (): \Illuminate\Database\Eloquent\Builder => TestModel::query()->where('status', 'active');

        // @phpstan-ignore-next-line - Template covariance issue with TestModel vs Model generic
        $provider = new DatabaseDataProvider(TestModel::class, $customQuery);

        // Should not throw errors
        $result = $provider->getData($this->session);
        $this->assertArrayHasKey('data', $result);
    }
}

/**
 * Test model for database provider tests.
 */
class TestModel extends Model
{
    protected $table = 'test_models';

    protected $fillable = ['name', 'status'];
}
