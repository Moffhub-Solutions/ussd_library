<?php

declare(strict_types=1);

namespace Moffhub\Ussd\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Moffhub\Ussd\Models\UssdMenuStatistic;

/**
 * @extends Factory<UssdMenuStatistic>
 */
class UssdMenuStatisticFactory extends Factory
{
    protected $model = UssdMenuStatistic::class;

    public function definition(): array
    {
        return [
            'menu_name' => 'main_menu',
            'option_selected' => (string) $this->faker->numberBetween(1, 9),
            'access_count' => $this->faker->numberBetween(1, 1000),
            'completion_count' => $this->faker->numberBetween(0, 500),
            'drop_off_count' => $this->faker->numberBetween(0, 200),
            'avg_time_spent' => $this->faker->randomFloat(2, 0.5, 30.0),
            'user_segments' => null,
            'date' => $this->faker->date(),
        ];
    }

    public function forMenu(string $menuName): static
    {
        return $this->state(fn (array $attributes): array => [
            'menu_name' => $menuName,
        ]);
    }

    public function forDate(string $date): static
    {
        return $this->state(fn (array $attributes): array => [
            'date' => $date,
        ]);
    }
}
