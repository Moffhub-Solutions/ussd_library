<?php

declare(strict_types=1);

namespace Moffhub\Ussd\Traits;

/**
 * Trait for menu name enums.
 *
 * Use this trait in your enum to add helpful methods for menu navigation.
 *
 * Example:
 * ```php
 * enum AppMenu: string implements MenuNameInterface
 * {
 *     use MenuEnumTrait;
 *
 *     case Main = 'main';
 *     case Balance = 'balance';
 *     case SendMoney = 'send_money';
 *     case BuyAirtime = 'buy_airtime';
 *     case Settings = 'settings';
 *
 *     public function label(): string
 *     {
 *         return match($this) {
 *             self::Main => 'Main Menu',
 *             self::Balance => 'Check Balance',
 *             self::SendMoney => 'Send Money',
 *             self::BuyAirtime => 'Buy Airtime',
 *             self::Settings => 'Settings',
 *         };
 *     }
 * }
 *
 * // Usage
 * $framework->registerMenu(AppMenu::Main, new SimpleMenu(...));
 * $framework->navigateToMenu(AppMenu::Balance);
 * ```
 */
trait MenuEnumTrait
{
    /**
     * Get the string value (implements MenuNameInterface).
     */
    public function value(): string
    {
        return $this->value;
    }

    /**
     * Get all menu values as an array.
     *
     * @return array<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * Get all menu names as an array.
     *
     * @return array<string>
     */
    public static function names(): array
    {
        return array_column(self::cases(), 'name');
    }

    /**
     * Create from string value.
     */
    public static function fromValue(string $value): ?static
    {
        foreach (self::cases() as $case) {
            if ($case->value === $value) {
                return $case;
            }
        }

        return null;
    }

    /**
     * Check if a value exists in the enum.
     */
    public static function hasValue(string $value): bool
    {
        return in_array($value, self::values(), true);
    }

    /**
     * Get menu as array for select options.
     * Requires the enum using this trait to implement a label(): string method.
     *
     * @return array<string, string>
     */
    public static function toSelectOptions(): array
    {
        $options = [];
        foreach (self::cases() as $case) {
            // @phpstan-ignore-next-line - label() is expected to be implemented by the enum using this trait
            $label = $case->label();
            $options[$case->value] = $label;
        }

        return $options;
    }
}
