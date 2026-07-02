<?php

declare(strict_types=1);

namespace Moffhub\Ussd\Support;

/**
 * Immutable, typed view over the framework's config array.
 *
 * This is additive: the framework still stores and exposes a plain array
 * (getConfig()). UssdConfig wraps it to give dot-notation access, typed getters,
 * and a recursive merge() so partial overrides no longer wipe sibling keys, the
 * class of bug that plain array_merge caused.
 */
final readonly class UssdConfig
{
    /**
     * @param  array<string, mixed>  $items
     */
    public function __construct(private array $items) {}

    /**
     * @param  array<string, mixed>  $items
     */
    public static function fromArray(array $items): self
    {
        return new self($items);
    }

    /**
     * Get a value by dot-notation key (e.g. "deduplication.window").
     */
    public function get(string $key, mixed $default = null): mixed
    {
        $value = $this->items;

        foreach (explode('.', $key) as $segment) {
            if (! is_array($value) || ! array_key_exists($segment, $value)) {
                return $default;
            }

            $value = $value[$segment];
        }

        return $value;
    }

    public function has(string $key): bool
    {
        $sentinel = "\0__missing__\0";

        return $this->get($key, $sentinel) !== $sentinel;
    }

    public function bool(string $key, bool $default = false): bool
    {
        $value = $this->get($key, $default);

        return is_bool($value) ? $value : (bool) $value;
    }

    public function int(string $key, int $default = 0): int
    {
        $value = $this->get($key, $default);

        return is_numeric($value) ? (int) $value : $default;
    }

    public function string(string $key, string $default = ''): string
    {
        $value = $this->get($key, $default);

        return is_scalar($value) ? (string) $value : $default;
    }

    /**
     * @param  array<mixed>  $default
     * @return array<mixed>
     */
    public function array(string $key, array $default = []): array
    {
        $value = $this->get($key, $default);

        return is_array($value) ? $value : $default;
    }

    // Convenience accessors for hot keys.

    public function debug(): bool
    {
        return $this->bool('debug');
    }

    public function sessionTimeout(): int
    {
        return $this->int('session_timeout', 300);
    }

    public function defaultMenu(): string
    {
        return $this->string('default_menu', 'main');
    }

    public function validateMenuReferences(): bool
    {
        return $this->bool('validate_menu_references', true);
    }

    public function deduplicationEnabled(): bool
    {
        return $this->bool('deduplication.enabled', true);
    }

    public function deduplicationWindow(): int
    {
        return $this->int('deduplication.window', 5);
    }

    /**
     * Return a new instance with the given overrides deep-merged in. Nested
     * blocks merge recursively instead of a partial override wiping siblings.
     *
     * @param  array<string, mixed>  $overrides
     */
    public function merge(array $overrides): self
    {
        return new self(array_replace_recursive($this->items, $overrides));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return $this->items;
    }
}
