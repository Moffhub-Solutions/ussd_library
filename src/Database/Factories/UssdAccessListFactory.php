<?php

declare(strict_types=1);

namespace Moffhub\Ussd\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Moffhub\Ussd\Models\UssdAccessList;

/**
 * @extends Factory<UssdAccessList>
 */
class UssdAccessListFactory extends Factory
{
    protected $model = UssdAccessList::class;

    public function definition(): array
    {
        return [
            'phone_number' => $this->faker->e164PhoneNumber(),
            'type' => 'whitelist',
            'reason' => $this->faker->sentence(),
            'added_by' => $this->faker->name(),
            'expires_at' => null,
            'is_active' => true,
            'metadata' => null,
        ];
    }

    public function whitelist(): static
    {
        return $this->state(fn (array $attributes): array => [
            'type' => 'whitelist',
        ]);
    }

    public function blacklist(): static
    {
        return $this->state(fn (array $attributes): array => [
            'type' => 'blacklist',
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes): array => [
            'is_active' => false,
        ]);
    }

    public function expired(): static
    {
        return $this->state(fn (array $attributes): array => [
            'expires_at' => now()->subDay(),
        ]);
    }

    public function expiresAt(\DateTimeInterface $date): static
    {
        return $this->state(fn (array $attributes): array => [
            'expires_at' => $date,
        ]);
    }
}
