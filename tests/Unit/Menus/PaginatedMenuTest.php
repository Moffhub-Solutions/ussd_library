<?php

declare(strict_types=1);

namespace Moffhub\Ussd\Tests\Unit\Menus;

use Illuminate\Http\Request;
use Moffhub\Ussd\Menus\PaginatedMenu;
use Moffhub\Ussd\Tests\TestCase;
use Moffhub\Ussd\UssdFramework;
use Moffhub\Ussd\UssdResponse;
use Moffhub\Ussd\UssdSession;

class PaginatedMenuTest extends TestCase
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

    // ==================== showInitial / page display ====================

    public function test_show_initial_displays_first_page(): void
    {
        $data = [
            '1' => 'Item One',
            '2' => 'Item Two',
            '3' => 'Item Three',
        ];

        $menu = new PaginatedMenu('Items List', $data, [
            'max_sms_length' => 500,
        ]);
        $menu->setFramework($this->framework);
        $menu->setConfig($this->framework->getConfig());

        $response = $menu->process('', $this->session);

        $this->assertTrue($response->isContinue());
        $this->assertStringContainsString('Items List', $response->getMessage());
        $this->assertStringContainsString('Page 1', $response->getMessage());
    }

    // ==================== Page navigation via processStep ====================

    public function test_next_page_navigation(): void
    {
        $data = [];
        for ($i = 1; $i <= 20; $i++) {
            $data[(string) $i] = "Item Number $i with a description";
        }

        $menu = new PaginatedMenu('Items', $data, [
            'max_sms_length' => 160,
            'reserve_chars' => 30,
        ]);
        $menu->setFramework($this->framework);
        $menu->setConfig($this->framework->getConfig());

        // Show initial to populate menu data
        $menu->process('', $this->session);

        // Call processStep directly with 'next' command
        $reflection = new \ReflectionClass($menu);
        $method = $reflection->getMethod('processStep');
        $method->setAccessible(true);

        $response = $method->invoke($menu, '00', 1, $this->session);

        $this->assertTrue($response->isContinue());
        $this->assertStringContainsString('Page 2', $response->getMessage());
    }

    // ==================== Boundary pages ====================

    public function test_last_page_next_shows_already_on_last_page(): void
    {
        $data = ['1' => 'Single Item'];

        $menu = new PaginatedMenu('Items', $data, [
            'max_sms_length' => 500,
        ]);
        $menu->setFramework($this->framework);
        $menu->setConfig($this->framework->getConfig());

        // Show initial
        $menu->process('', $this->session);

        // Try next on single page
        $reflection = new \ReflectionClass($menu);
        $method = $reflection->getMethod('processStep');
        $method->setAccessible(true);

        $response = $method->invoke($menu, '00', 1, $this->session);

        $this->assertTrue($response->isContinue());
        $this->assertStringContainsString('Already on last page', $response->getMessage());
    }

    // ==================== Empty data set ====================

    public function test_empty_data_shows_empty_message(): void
    {
        $menu = new PaginatedMenu('Items', [], [
            'empty_message' => 'No items found.',
        ]);
        $menu->setFramework($this->framework);
        $menu->setConfig($this->framework->getConfig());

        $response = $menu->process('', $this->session);

        $this->assertTrue($response->isContinue());
        $this->assertStringContainsString('No items found', $response->getMessage());
    }

    public function test_custom_empty_message(): void
    {
        $menu = new PaginatedMenu('Products', [], [
            'empty_message' => 'No products available at this time.',
        ]);
        $menu->setFramework($this->framework);
        $menu->setConfig($this->framework->getConfig());

        $response = $menu->process('', $this->session);

        $this->assertStringContainsString('No products available', $response->getMessage());
    }

    // ==================== Single page data set ====================

    public function test_single_page_shows_page_info(): void
    {
        $data = ['1' => 'Only Item'];

        $menu = new PaginatedMenu('Items', $data, [
            'max_sms_length' => 500,
            'show_navigation_help' => false,
        ]);
        $menu->setFramework($this->framework);
        $menu->setConfig($this->framework->getConfig());

        $response = $menu->process('', $this->session);

        $this->assertTrue($response->isContinue());
        $this->assertStringContainsString('Page 1 of 1', $response->getMessage());
    }

    // ==================== Item selection via processStep ====================

    public function test_item_selection_with_action(): void
    {
        $selectedKey = null;
        $selectedItem = null;

        $data = ['1' => 'Item One', '2' => 'Item Two'];

        $menu = new PaginatedMenu('Items', $data, [
            'max_sms_length' => 500,
            'item_action' => function ($key, $item, $session, $framework) use (&$selectedKey, &$selectedItem) {
                $selectedKey = $key;
                $selectedItem = $item;

                return UssdResponse::end("You picked: $item");
            },
        ]);
        $menu->setFramework($this->framework);
        $menu->setConfig($this->framework->getConfig());

        // Show initial (populates menu data)
        $menu->process('', $this->session);

        // Call handleItemSelection directly
        $reflection = new \ReflectionClass($menu);
        $method = $reflection->getMethod('handleItemSelection');
        $method->setAccessible(true);

        $response = $method->invoke($menu, '1', $this->session);

        $this->assertTrue($response->isEnd());
        $this->assertStringContainsString('You picked: Item One', $response->getMessage());
    }

    public function test_invalid_selection_shows_error(): void
    {
        $data = ['1' => 'Item One'];

        $menu = new PaginatedMenu('Items', $data, [
            'max_sms_length' => 500,
        ]);
        $menu->setFramework($this->framework);
        $menu->setConfig($this->framework->getConfig());

        // Show initial
        $menu->process('', $this->session);

        $reflection = new \ReflectionClass($menu);
        $method = $reflection->getMethod('handleItemSelection');
        $method->setAccessible(true);

        $response = $method->invoke($menu, 'abc', $this->session);

        $this->assertTrue($response->isContinue());
        $this->assertStringContainsString('Invalid selection', $response->getMessage());
    }

    public function test_selection_not_found_shows_error(): void
    {
        $data = ['1' => 'Item One'];

        $menu = new PaginatedMenu('Items', $data, [
            'max_sms_length' => 500,
        ]);
        $menu->setFramework($this->framework);
        $menu->setConfig($this->framework->getConfig());

        // Show initial
        $menu->process('', $this->session);

        $reflection = new \ReflectionClass($menu);
        $method = $reflection->getMethod('handleItemSelection');
        $method->setAccessible(true);

        $response = $method->invoke($menu, '99', $this->session);

        $this->assertTrue($response->isContinue());
        $this->assertStringContainsString('not found', $response->getMessage());
    }

    // ==================== Data provider types ====================

    public function test_callable_data_provider(): void
    {
        $provider = fn ($session, $filters) => ['1' => 'Dynamic Item'];

        $menu = new PaginatedMenu('Dynamic', $provider, [
            'max_sms_length' => 500,
        ]);
        $menu->setFramework($this->framework);
        $menu->setConfig($this->framework->getConfig());

        $response = $menu->process('', $this->session);

        $this->assertStringContainsString('Dynamic Item', $response->getMessage());
    }

    // ==================== Item formatter ====================

    public function test_custom_item_formatter(): void
    {
        $data = ['1' => ['name' => 'Apple', 'price' => 100]];

        $menu = new PaginatedMenu('Products', $data, [
            'max_sms_length' => 500,
            'item_formatter' => fn ($key, $item) => "{$item['name']} - KES {$item['price']}",
        ]);
        $menu->setFramework($this->framework);
        $menu->setConfig($this->framework->getConfig());

        $response = $menu->process('', $this->session);

        $this->assertStringContainsString('Apple - KES 100', $response->getMessage());
    }

    // ==================== Setters ====================

    public function test_set_filters_returns_static(): void
    {
        $menu = new PaginatedMenu('Title', []);
        $result = $menu->setFilters(['active' => true]);

        $this->assertSame($menu, $result);
    }

    public function test_set_item_action_returns_static(): void
    {
        $menu = new PaginatedMenu('Title', []);
        $result = $menu->setItemAction(fn () => UssdResponse::end('Done'));

        $this->assertSame($menu, $result);
    }

    public function test_set_item_formatter_returns_static(): void
    {
        $menu = new PaginatedMenu('Title', []);
        $result = $menu->setItemFormatter(fn ($key, $item) => (string) $item);

        $this->assertSame($menu, $result);
    }

    // ==================== Default item formatter ====================

    public function test_default_formatter_handles_string_items(): void
    {
        $data = ['1' => 'Simple String'];

        $menu = new PaginatedMenu('Items', $data, [
            'max_sms_length' => 500,
        ]);
        $menu->setFramework($this->framework);
        $menu->setConfig($this->framework->getConfig());

        $response = $menu->process('', $this->session);

        $this->assertStringContainsString('1. Simple String', $response->getMessage());
    }

    public function test_default_formatter_handles_array_with_name(): void
    {
        $data = ['1' => ['name' => 'Product A']];

        $menu = new PaginatedMenu('Items', $data, [
            'max_sms_length' => 500,
        ]);
        $menu->setFramework($this->framework);
        $menu->setConfig($this->framework->getConfig());

        $response = $menu->process('', $this->session);

        $this->assertStringContainsString('Product A', $response->getMessage());
    }

    // ==================== SMS-based pagination ====================

    public function test_sms_based_pagination_splits_correctly(): void
    {
        $data = [];
        for ($i = 1; $i <= 10; $i++) {
            $data[(string) $i] = "Item $i with a longer description text for testing";
        }

        $menu = new PaginatedMenu('Items', $data, [
            'max_sms_length' => 160,
            'reserve_chars' => 50,
        ]);
        $menu->setFramework($this->framework);
        $menu->setConfig($this->framework->getConfig());

        $response = $menu->process('', $this->session);

        $menuData = $this->session->getMenuData();
        $this->assertGreaterThan(1, $menuData['total_pages']);
    }

    // ==================== Navigation help ====================

    public function test_navigation_help_shown_when_enabled(): void
    {
        $data = ['1' => 'Item'];

        $menu = new PaginatedMenu('Items', $data, [
            'max_sms_length' => 500,
            'show_navigation_help' => true,
        ]);
        $menu->setFramework($this->framework);
        $menu->setConfig($this->framework->getConfig());

        $response = $menu->process('', $this->session);

        $this->assertStringContainsString('Commands:', $response->getMessage());
    }
}
