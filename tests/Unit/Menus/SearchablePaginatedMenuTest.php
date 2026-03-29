<?php

declare(strict_types=1);

namespace Moffhub\Ussd\Tests\Unit\Menus;

use Illuminate\Http\Request;
use Moffhub\Ussd\Interfaces\DataProviderInterface;
use Moffhub\Ussd\Menus\SearchablePaginatedMenu;
use Moffhub\Ussd\Tests\TestCase;
use Moffhub\Ussd\UssdFramework;
use Moffhub\Ussd\UssdSession;

class SearchablePaginatedMenuTest extends TestCase
{
    private UssdSession $session;

    private UssdFramework $framework;

    protected function setUp(): void
    {
        parent::setUp();
        $this->session = new UssdSession('+254712345678', 'test_session');
        $this->framework = new UssdFramework([
            'navigation' => ['back' => '99', 'home' => '0', 'next' => '00', 'search' => '98'],
            'global_navigation' => ['enabled' => false],
            'default_menu' => 'main',
        ]);
        $this->framework->setSession($this->session);
        $this->framework->setRequest(Request::create('/', 'POST', [
            'phoneNumber' => '+254712345678',
            'sessionId' => 'test_session',
        ]));
    }

    private function createDataProvider(array $data): DataProviderInterface
    {
        return new class($data) implements DataProviderInterface
        {
            public function __construct(private array $data) {}

            public function getData(mixed $session = null, array $filters = []): array
            {
                return [
                    'data' => $this->data,
                    'total' => count($this->data),
                    'current_page' => 1,
                    'per_page' => count($this->data),
                    'has_more' => false,
                ];
            }

            public function getItem(string|int $id, mixed $session): mixed
            {
                return $this->data[$id] ?? null;
            }

            public function search(string $query, mixed $session, array $fields = []): array
            {
                $results = array_filter($this->data, function ($item) use ($query, $fields) {
                    if (is_array($item)) {
                        foreach ($fields as $field) {
                            if (isset($item[$field]) && str_contains(strtolower((string) $item[$field]), strtolower($query))) {
                                return true;
                            }
                        }

                        return false;
                    }

                    return str_contains(strtolower((string) $item), strtolower($query));
                });

                return ['data' => $results, 'total' => count($results)];
            }
        };
    }

    // ==================== Initial display ====================

    public function test_show_initial_clears_search_query(): void
    {
        $provider = $this->createDataProvider(['1' => ['name' => 'Apple']]);

        $menu = new SearchablePaginatedMenu('Fruits', $provider, [
            'max_sms_length' => 500,
            'searchable' => true,
            'search_fields' => ['name'],
        ]);
        $menu->setFramework($this->framework);
        $menu->setConfig($this->framework->getConfig());

        $menu->process('', $this->session);

        $this->assertEquals('', $this->session->getFormData('search_query'));
    }

    public function test_show_initial_displays_items(): void
    {
        $provider = $this->createDataProvider([
            '1' => ['name' => 'Apple'],
            '2' => ['name' => 'Banana'],
        ]);

        $menu = new SearchablePaginatedMenu('Fruits', $provider, [
            'max_sms_length' => 500,
            'searchable' => true,
        ]);
        $menu->setFramework($this->framework);
        $menu->setConfig($this->framework->getConfig());

        $response = $menu->process('', $this->session);

        $this->assertTrue($response->isContinue());
        $this->assertStringContainsString('Fruits', $response->getMessage());
    }

    // ==================== Search via processStep ====================

    public function test_search_filters_results(): void
    {
        $provider = $this->createDataProvider([
            '1' => ['name' => 'Apple'],
            '2' => ['name' => 'Banana'],
            '3' => ['name' => 'Apricot'],
        ]);

        $menu = new SearchablePaginatedMenu('Fruits', $provider, [
            'max_sms_length' => 500,
            'searchable' => true,
            'search_fields' => ['name'],
        ]);
        $menu->setFramework($this->framework);
        $menu->setConfig($this->framework->getConfig());

        // Show initial
        $menu->process('', $this->session);

        // Call processStep directly with search term
        $reflection = new \ReflectionClass($menu);
        $method = $reflection->getMethod('processStep');
        $method->setAccessible(true);

        $response = $method->invoke($menu, 'apple', 1, $this->session);

        $this->assertTrue($response->isContinue());
        $this->assertStringContainsString('Apple', $response->getMessage());
    }

    // ==================== No results scenario ====================

    public function test_search_no_results_shows_empty(): void
    {
        $provider = $this->createDataProvider([
            '1' => ['name' => 'Apple'],
            '2' => ['name' => 'Banana'],
        ]);

        $menu = new SearchablePaginatedMenu('Fruits', $provider, [
            'max_sms_length' => 500,
            'searchable' => true,
            'search_fields' => ['name'],
        ]);
        $menu->setFramework($this->framework);
        $menu->setConfig($this->framework->getConfig());

        // Show initial
        $menu->process('', $this->session);

        // Search for non-existent item
        $reflection = new \ReflectionClass($menu);
        $method = $reflection->getMethod('processStep');
        $method->setAccessible(true);

        $response = $method->invoke($menu, 'xyz123', 1, $this->session);

        $this->assertTrue($response->isContinue());
        $this->assertStringContainsString('No items found', $response->getMessage());
    }

    // ==================== Navigation options include search ====================

    public function test_navigation_options_include_search_command(): void
    {
        $provider = $this->createDataProvider(['1' => ['name' => 'Apple']]);

        $menu = new SearchablePaginatedMenu('Fruits', $provider, [
            'max_sms_length' => 500,
            'searchable' => true,
        ]);
        $menu->setFramework($this->framework);
        $menu->setConfig($this->framework->getConfig());

        $response = $menu->process('', $this->session);

        $this->assertStringContainsString('Search', $response->getMessage());
    }

    // ==================== Search initiation command ====================

    public function test_search_command_initiates_search(): void
    {
        $provider = $this->createDataProvider(['1' => ['name' => 'Apple']]);

        $menu = new SearchablePaginatedMenu('Fruits', $provider, [
            'max_sms_length' => 500,
            'searchable' => true,
        ]);
        $menu->setFramework($this->framework);
        $menu->setConfig($this->framework->getConfig());

        // Show initial
        $menu->process('', $this->session);

        // Call handleNavigationCommand directly
        $reflection = new \ReflectionClass($menu);
        $method = $reflection->getMethod('handleNavigationCommand');
        $method->setAccessible(true);

        $response = $method->invoke($menu, '98', $this->session);

        $this->assertTrue($response->isContinue());
        $this->assertStringContainsString('search term', $response->getMessage());
    }

    // ==================== Search with multiple fields ====================

    public function test_search_with_multiple_fields(): void
    {
        $provider = $this->createDataProvider([
            '1' => ['name' => 'Apple', 'description' => 'Red fruit'],
            '2' => ['name' => 'Banana', 'description' => 'Yellow fruit'],
        ]);

        $menu = new SearchablePaginatedMenu('Fruits', $provider, [
            'max_sms_length' => 500,
            'searchable' => true,
            'search_fields' => ['name', 'description'],
        ]);
        $menu->setFramework($this->framework);
        $menu->setConfig($this->framework->getConfig());

        // Show initial
        $menu->process('', $this->session);

        // Search by description field
        $reflection = new \ReflectionClass($menu);
        $method = $reflection->getMethod('showSearchResults');
        $method->setAccessible(true);

        $response = $method->invoke($menu, 'yellow', $this->session);

        $this->assertTrue($response->isContinue());
        $this->assertStringContainsString('Banana', $response->getMessage());
    }

    // ==================== Non-searchable mode ====================

    public function test_non_searchable_does_not_show_search(): void
    {
        $provider = $this->createDataProvider(['1' => ['name' => 'Apple']]);

        $menu = new SearchablePaginatedMenu('Fruits', $provider, [
            'max_sms_length' => 500,
            'searchable' => false,
        ]);
        $menu->setFramework($this->framework);
        $menu->setConfig($this->framework->getConfig());

        $response = $menu->process('', $this->session);

        // Should not contain search option when searchable is false
        $this->assertStringNotContainsString('98 Search', $response->getMessage());
    }

    // ==================== Filter data ====================

    public function test_filter_data_with_string_items(): void
    {
        $provider = $this->createDataProvider([
            '1' => 'apple pie',
            '2' => 'banana split',
            '3' => 'apple sauce',
        ]);

        $menu = new SearchablePaginatedMenu('Items', $provider, [
            'max_sms_length' => 500,
            'searchable' => true,
        ]);
        $menu->setFramework($this->framework);
        $menu->setConfig($this->framework->getConfig());

        $reflection = new \ReflectionClass($menu);
        $method = $reflection->getMethod('filterData');
        $method->setAccessible(true);

        $data = ['1' => 'apple pie', '2' => 'banana split', '3' => 'apple sauce'];
        $filtered = $method->invoke($menu, $data, 'apple');

        $this->assertCount(2, $filtered);
        $this->assertArrayHasKey('1', $filtered);
        $this->assertArrayHasKey('3', $filtered);
    }
}
