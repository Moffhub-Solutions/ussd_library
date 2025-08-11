<?php

declare(strict_types=1);

namespace Moffhub\Ussd\Tests\Unit;

use Illuminate\Http\Request;
use Moffhub\Ussd\DataProviders\ArrayDataProvider;
use Moffhub\Ussd\Tests\TestCase;
use Moffhub\Ussd\UssdSession;

class ArrayDataProviderTest extends TestCase
{
    private function makeSession(): UssdSession
    {
        $request = Request::create(
            '/', 'POST', [
                'phoneNumber' => '254700000000',
                'sessionId' => 'sess-1',
            ]
        );

        return new UssdSession($request);
    }

    public function test_get_data_filters_by_field(): void
    {
        $provider = new ArrayDataProvider([
            ['id' => 1, 'name' => 'Alpha', 'type' => 'A'],
            ['id' => 2, 'name' => 'Beta', 'type' => 'B'],
            ['id' => 3, 'name' => 'Gamma', 'type' => 'A'],
        ]);

        $out = $provider->getData($this->makeSession(), ['type' => 'A'])['data'];
        $this->assertCount(2, $out);
        $this->assertSame(1, $out[0]['id']);
        $this->assertSame(3, $out[1]['id']);
    }

    public function test_get_item_finds_by_id_in_array(): void
    {
        $provider = new ArrayDataProvider([
            ['id' => 10, 'name' => 'Ten'],
            ['id' => 11, 'name' => 'Eleven'],
        ]);

        $item = $provider->getItem(11, $this->makeSession());
        $this->assertIsArray($item);
        $this->assertSame('Eleven', $item['name']);
    }

    public function test_get_item_finds_scalar(): void
    {
        $provider = new ArrayDataProvider([1, 2, 3]);
        $item = $provider->getItem(2, $this->makeSession());
        $this->assertSame(2, $item);
    }

    public function test_search_returns_matching_items(): void
    {
        $provider = new ArrayDataProvider([
            ['id' => 1, 'title' => 'Red Apple'],
            ['id' => 2, 'title' => 'Green Banana'],
            ['id' => 3, 'title' => 'Blueberry'],
        ]);

        $result = $provider->search('apple', $this->makeSession(), ['title']);
        $this->assertSame(1, $result['total']);
        $this->assertSame(1, $result['data'][0]['id']);
    }
}
