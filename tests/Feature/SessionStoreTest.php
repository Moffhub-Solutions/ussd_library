<?php

declare(strict_types=1);

namespace Moffhub\Ussd\Tests\Feature;

use Moffhub\Ussd\Interfaces\SessionStoreInterface;
use Moffhub\Ussd\Menus\SimpleMenu;
use Moffhub\Ussd\Testing\UssdTester;
use Moffhub\Ussd\Tests\TestCase;
use Moffhub\Ussd\UssdResponse;

class SessionStoreTest extends TestCase
{
    public function test_bound_store_is_used_and_preserves_continuity(): void
    {
        $store = new class implements SessionStoreInterface
        {
            /** @var array<string, array<string, mixed>> */
            public array $data = [];

            public function get(string $key): ?array
            {
                return $this->data[$key] ?? null;
            }

            public function put(string $key, array $value, int $ttlSeconds): void
            {
                $this->data[$key] = $value;
            }

            public function has(string $key): bool
            {
                return isset($this->data[$key]);
            }

            public function forget(string $key): void
            {
                unset($this->data[$key]);
            }
        };

        app()->instance(SessionStoreInterface::class, $store);

        $tester = UssdTester::fake(['default_menu' => 'main'])
            ->register('main', new SimpleMenu('Main', ['1' => 'Finish'], [
                '1' => fn (): UssdResponse => UssdResponse::end('Done'),
            ]));

        $tester->dial()->assertSee('Main');

        // The custom store now holds the live session (not Laravel's cache).
        $this->assertNotEmpty($store->data);

        // Continuity works through the custom store.
        $tester->send('1')->assertEnded()->assertSee('Done');
    }
}
