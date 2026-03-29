<?php

declare(strict_types=1);

namespace Moffhub\Ussd\Facades;

use Illuminate\Support\Facades\Facade;
use Moffhub\Ussd\UssdFramework;

/**
 * @method static \Moffhub\Ussd\UssdResponse handle(\Illuminate\Http\Request $request)
 * @method static static registerMenu(string|\Moffhub\Ussd\Interfaces\MenuNameInterface|\BackedEnum $name, \Moffhub\Ussd\Interfaces\UssdMenuInterface $menu)
 * @method static static registerMenus(array $menus)
 * @method static \Moffhub\Ussd\Interfaces\UssdMenuInterface getMenu(string|\Moffhub\Ussd\Interfaces\MenuNameInterface|\BackedEnum $name)
 * @method static bool hasMenu(string|\Moffhub\Ussd\Interfaces\MenuNameInterface|\BackedEnum $name)
 * @method static mixed getConfig(?string $key = null, mixed $default = null)
 * @method static array getHealthStatus()
 * @method static self setProvider(\Moffhub\Ussd\Interfaces\UssdProviderInterface $provider)
 * @method static ?\Moffhub\Ussd\Interfaces\UssdProviderInterface getCurrentProvider()
 * @method static ?\Moffhub\Ussd\UssdSession getSession()
 *
 * @see UssdFramework
 */
class Ussd extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return UssdFramework::class;
    }
}
