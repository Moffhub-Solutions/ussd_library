<?php

declare(strict_types=1);

namespace Moffhub\Ussd\Tests\Unit\Menus;

use Illuminate\Http\Request;
use Moffhub\Ussd\Menus\UssdMenu;
use Moffhub\Ussd\Tests\TestCase;
use Moffhub\Ussd\UssdFramework;
use Moffhub\Ussd\UssdSession;

/**
 * The base UssdMenu paginated path used to call the data-provider callable with
 * one arg ($session) and the item_formatter with two ($key, $item), while
 * PaginatedMenu passed ($session, $filters) and ($key, $item, $page). A closure
 * written to PaginatedMenu's (wider) contract threw ArgumentCountError on the
 * base path. The base path now passes the same extra args.
 */
class CallbackArgAlignmentTest extends TestCase
{
    private UssdSession $session;

    private UssdFramework $framework;

    protected function setUp(): void
    {
        parent::setUp();
        $this->session = new UssdSession('+254712345678', 'test_session');
        $this->framework = new UssdFramework(['default_menu' => 'products']);
        $this->framework->setSession($this->session);
        $this->framework->setRequest(Request::create('/', 'POST', [
            'phoneNumber' => '+254712345678',
            'sessionId' => 'test_session',
        ]));
    }

    public function test_base_path_accepts_wide_provider_and_formatter_signatures(): void
    {
        $menu = new UssdMenu('Products');
        $menu->setType('paginated');

        // Requires the second ($filters) arg: would ArgumentCountError before the fix.
        $menu->setDataProvider(function (UssdSession $session, array $filters): array {
            return [
                'p1' => ['id' => 'p1', 'name' => 'Widget'],
                'p2' => ['id' => 'p2', 'name' => 'Gadget'],
            ];
        });

        // Requires the third ($page) arg: would ArgumentCountError before the fix.
        $menu->setItemFormatter(function ($key, $item, int $page): string {
            return $item['name']." (p{$page})";
        });

        $menu->setFramework($this->framework);
        $menu->setConfig($this->framework->getConfig());

        $response = $menu->process('', $this->session);

        $this->assertTrue($response->isContinue());
        $this->assertStringContainsString('Widget (p1)', $response->getMessage());
        $this->assertStringContainsString('Gadget (p1)', $response->getMessage());
    }
}
