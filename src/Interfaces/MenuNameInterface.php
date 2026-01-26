<?php

declare(strict_types=1);

namespace Moffhub\Ussd\Interfaces;

/**
 * Interface for menu name enums.
 *
 * Implement this interface on your enum to enable type-safe menu navigation.
 *
 * Example:
 * ```php
 * enum AppMenu: string implements MenuNameInterface
 * {
 *     case Main = 'main';
 *     case Balance = 'balance';
 *     case SendMoney = 'send_money';
 *
 *     public function value(): string
 *     {
 *         return $this->value;
 *     }
 * }
 * ```
 */
interface MenuNameInterface
{
    /**
     * Get the string value of the menu name.
     */
    public function value(): string;
}
