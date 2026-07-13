<?php

declare(strict_types=1);

namespace Moffhub\Ussd\Tests\Unit\Traits;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Moffhub\Ussd\Tests\TestCase;
use Moffhub\Ussd\Traits\HasUlidRouteKey;

class HasUlidRouteKeyTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('ulid_route_key_models', function (Blueprint $table): void {
            $table->increments('id');
            $table->char('ulid', 26)->unique();
            $table->string('name');
        });
    }

    private function makeModel(array $attributes): UlidRouteKeyModel
    {
        $model = new UlidRouteKeyModel($attributes);
        $model->save();

        return $model;
    }

    public function test_it_fills_a_lowercase_ulid_on_create(): void
    {
        $ulid = (string) $this->makeModel(['name' => 'Alpha'])->getAttribute('ulid');

        $this->assertSame(26, strlen($ulid));
        $this->assertSame(strtolower($ulid), $ulid);
    }

    public function test_it_keeps_an_explicitly_supplied_ulid(): void
    {
        $model = $this->makeModel([
            'name' => 'Beta',
            'ulid' => '01hqz4v9m2n8p3r6t0w5x7y9zb',
        ]);

        $this->assertSame('01hqz4v9m2n8p3r6t0w5x7y9zb', $model->getAttribute('ulid'));
    }

    public function test_it_routes_on_the_ulid_and_keeps_the_numeric_key(): void
    {
        $model = $this->makeModel(['name' => 'Gamma']);

        $this->assertSame('ulid', $model->getRouteKeyName());
        $this->assertSame($model->getAttribute('ulid'), $model->getRouteKey());
        $this->assertSame('id', $model->getKeyName());
    }
}

class UlidRouteKeyModel extends Model
{
    use HasUlidRouteKey;

    public $timestamps = false;

    protected $table = 'ulid_route_key_models';

    protected $guarded = [];
}
