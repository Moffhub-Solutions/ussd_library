<?php

declare(strict_types=1);

namespace Moffhub\Ussd\Interfaces;

use BackedEnum;

/**
 * A menu that can declare, up front, the menus it navigates to.
 *
 * The framework uses this at build time to verify every declared target
 * resolves to a registered menu, turning a typo or a rename into a clear boot
 * error instead of a blank screen at runtime. Navigation performed inside
 * opaque closures cannot be inspected and is not covered.
 */
interface DeclaresNavigationTargets
{
    /**
     * The menus this menu navigates to (names, enum cases, or MenuNameInterface).
     *
     * @return array<int, string|MenuNameInterface|BackedEnum>
     */
    public function navigationTargets(): array;
}
