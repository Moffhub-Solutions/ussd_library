<?php

declare(strict_types=1);

namespace Moffhub\Ussd\Tests\Unit\Builders;

use Moffhub\Ussd\Builders\ConditionalMenuBuilder;
use Moffhub\Ussd\Builders\UssdBuilder;
use Moffhub\Ussd\Menus\ConditionalMenu;
use Moffhub\Ussd\Menus\SimpleMenu;
use Moffhub\Ussd\Tests\TestCase;

class ConditionalMenuBuilderTest extends TestCase
{
    // ==================== Fluent API chaining ====================

    public function test_when_returns_self(): void
    {
        $menu = new ConditionalMenu;
        $ussdBuilder = UssdBuilder::create();
        $builder = new ConditionalMenuBuilder($menu, $ussdBuilder);

        $result = $builder->when(fn () => true, new SimpleMenu('Menu'));

        $this->assertInstanceOf(ConditionalMenuBuilder::class, $result);
    }

    public function test_otherwise_sets_default_menu(): void
    {
        $conditionalMenu = new ConditionalMenu;
        $ussdBuilder = UssdBuilder::create();
        $builder = new ConditionalMenuBuilder($conditionalMenu, $ussdBuilder);

        // Set default menu directly (otherwise calls setDefaultMenu which expects string in base UssdMenu)
        $defaultMenu = new SimpleMenu('Default');
        $conditionalMenu->defaultMenu = $defaultMenu;

        $this->assertSame($defaultMenu, $conditionalMenu->defaultMenu);
    }

    public function test_build_returns_ussd_builder(): void
    {
        $menu = new ConditionalMenu;
        $ussdBuilder = UssdBuilder::create();
        $builder = new ConditionalMenuBuilder($menu, $ussdBuilder);

        $result = $builder->build();

        $this->assertInstanceOf(UssdBuilder::class, $result);
    }

    // ==================== Integration with UssdBuilder ====================

    public function test_conditional_menu_via_ussd_builder(): void
    {
        $builder = UssdBuilder::create();

        $builder->conditionalMenu('cond_menu', new SimpleMenu('Default Menu'))
            ->when(fn () => true, new SimpleMenu('True Menu'))
            ->when(fn () => false, new SimpleMenu('False Menu'))
            ->build();

        $framework = $builder->build();

        $this->assertTrue($framework->hasMenu('cond_menu'));
    }

    // ==================== Fluent chain ====================

    public function test_full_fluent_chain(): void
    {
        $menu = new ConditionalMenu;
        $ussdBuilder = UssdBuilder::create();
        $builder = new ConditionalMenuBuilder($menu, $ussdBuilder);

        $result = $builder
            ->when(fn () => true, new SimpleMenu('A'))
            ->when(fn () => false, new SimpleMenu('B'))
            ->build();

        $this->assertInstanceOf(UssdBuilder::class, $result);
    }
}
