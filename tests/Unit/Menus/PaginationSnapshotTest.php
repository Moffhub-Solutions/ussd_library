<?php

declare(strict_types=1);

namespace Moffhub\Ussd\Tests\Unit\Menus;

use Illuminate\Http\Request;
use Moffhub\Ussd\Interfaces\DataProviderInterface;
use Moffhub\Ussd\Menus\SearchablePaginatedMenu;
use Moffhub\Ussd\Menus\UssdMenu;
use Moffhub\Ussd\Tests\TestCase;
use Moffhub\Ussd\UssdFramework;
use Moffhub\Ussd\UssdSession;

/**
 * B: selection must resolve against the exact set shown to the user, even when
 *    the data provider returns rows in a different order on a later request.
 * C: a search filter must persist across pages instead of reverting to the full
 *    dataset when the user pages forward.
 */
class PaginationSnapshotTest extends TestCase
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
            'items_per_page' => 5,
        ]);
        $this->framework->setSession($this->session);
        $this->framework->setRequest(Request::create('/', 'POST', [
            'phoneNumber' => '+254712345678',
            'sessionId' => 'test_session',
        ]));
    }

    public function test_selection_uses_the_snapshot_not_a_reordered_refetch(): void
    {
        $calls = 0;
        $menu = new UssdMenu('People');
        $menu->setType('paginated');
        // Order flips on each call: first render [Alice, Bob], a refetch would be [Bob, Alice].
        $menu->setDataProvider(function () use (&$calls): array {
            $calls++;
            $items = [
                'a' => ['id' => 'a', 'name' => 'Alice'],
                'b' => ['id' => 'b', 'name' => 'Bob'],
            ];

            return $calls % 2 === 1 ? $items : array_reverse($items, true);
        });
        $menu->setFramework($this->framework);
        $menu->setConfig($this->framework->getConfig());

        $display = $menu->process('', $this->session);
        $this->assertStringContainsString('1. Alice', $display->getMessage());
        $this->assertStringContainsString('2. Bob', $display->getMessage());

        // Selecting "2" must yield Bob (what was shown), not Alice (a reordered refetch).
        $selected = $menu->process('2', $this->session);
        $this->assertStringContainsString('Bob', $selected->getMessage());
        $this->assertStringNotContainsString('Alice', $selected->getMessage());
    }

    public function test_search_filter_persists_across_pages(): void
    {
        // 6 matches for " team" + 2 non-matches; tiny SMS budget forces >1 page.
        $data = [];
        for ($i = 1; $i <= 6; $i++) {
            $data['t'.$i] = ['id' => 't'.$i, 'name' => "team member {$i}"];
        }
        $data['x1'] = ['id' => 'x1', 'name' => 'solo one'];
        $data['x2'] = ['id' => 'x2', 'name' => 'solo two'];

        $provider = new class($data) implements DataProviderInterface
        {
            public function __construct(private array $data) {}

            public function getData(mixed $session = null, array $filters = []): array
            {
                return [
                    'data' => array_values($this->data),
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
                return [
                    'data' => array_values($this->data),
                    'total' => count($this->data),
                ];
            }
        };

        $menu = new SearchablePaginatedMenu('Directory', $provider, [
            'search_fields' => ['name'],
            'max_sms_length' => 80,
        ]);
        $menu->setFramework($this->framework);
        $menu->setConfig($this->framework->getConfig());

        $menu->process('', $this->session);        // initial page

        $step = new \ReflectionMethod($menu, 'processStep');
        $step->setAccessible(true);

        $page1 = $step->invoke($menu, 'team', 1, $this->session);   // search term -> filtered page 1
        $this->assertStringContainsString('team member', $page1->getMessage());
        $this->assertStringNotContainsString('solo', $page1->getMessage());
        $this->assertStringContainsString('Next', $page1->getMessage(), 'filtered results should span >1 page');

        $page2 = $step->invoke($menu, '00', 1, $this->session);     // next page must stay filtered
        $this->assertStringContainsString('team member', $page2->getMessage());
        $this->assertStringNotContainsString('solo', $page2->getMessage());
    }
}
