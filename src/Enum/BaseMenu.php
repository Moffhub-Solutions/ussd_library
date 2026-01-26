<?php

declare(strict_types=1);

namespace Moffhub\Ussd\Enum;

use Moffhub\Ussd\Interfaces\MenuNameInterface;
use Moffhub\Ussd\Traits\MenuEnumTrait;

/**
 * Base menu names provided by the framework.
 *
 * Users should create their own enum that implements MenuNameInterface
 * for application-specific menus. Use the MenuEnumTrait for helpful methods.
 *
 * Example usage:
 * ```php
 * // Using the framework's base menus
 * $framework->registerMenu(BaseMenu::Main, new SimpleMenu(...));
 * $framework->navigateToMenu(BaseMenu::Main);
 *
 * // Create your own enum extending the base functionality
 * enum MyAppMenu: string implements MenuNameInterface
 * {
 *     use MenuEnumTrait;
 *
 *     case Dashboard = 'dashboard';
 *     case Balance = 'balance';
 *     case SendMoney = 'send_money';
 *     case Settings = 'settings';
 *
 *     public function label(): string
 *     {
 *         return match($this) {
 *             self::Dashboard => 'Dashboard',
 *             self::Balance => 'Check Balance',
 *             self::SendMoney => 'Send Money',
 *             self::Settings => 'Settings',
 *         };
 *     }
 * }
 *
 * // Then use it in your application
 * $framework->registerMenu(MyAppMenu::Dashboard, new SimpleMenu('Dashboard', [...]));
 * $framework->navigateToMenu(MyAppMenu::Balance);
 * ```
 */
enum BaseMenu: string implements MenuNameInterface
{
    use MenuEnumTrait;

    case Main = 'main';
    case Home = 'home';
    case Back = 'back';
    case Error = 'error';
    case Timeout = 'timeout';
    case Exit = 'exit';

    /**
     * Get display name for the menu.
     */
    public function label(): string
    {
        return match ($this) {
            self::Main => 'Main Menu',
            self::Home => 'Home',
            self::Back => 'Back',
            self::Error => 'Error',
            self::Timeout => 'Session Timeout',
            self::Exit => 'Exit',
        };
    }
}
