<?php

declare(strict_types=1);

namespace Moffhub\Ussd\Tests\Unit\Builders;

use Moffhub\Ussd\Builders\UnifiedMenuBuilder;
use Moffhub\Ussd\Builders\UssdBuilder;
use Moffhub\Ussd\Menus\UssdMenu;
use Moffhub\Ussd\Tests\TestCase;
use Moffhub\Ussd\UssdResponse;

class UnifiedMenuBuilderTest extends TestCase
{
    private UssdMenu $menu;

    private UssdBuilder $ussdBuilder;

    private UnifiedMenuBuilder $builder;

    protected function setUp(): void
    {
        parent::setUp();
        $this->menu = new UssdMenu('Test Menu');
        $this->ussdBuilder = UssdBuilder::create();
        $this->builder = new UnifiedMenuBuilder($this->menu, $this->ussdBuilder);
    }

    // ==================== Fluent API chaining ====================

    public function test_set_type_returns_self(): void
    {
        $result = $this->builder->setType('form');

        $this->assertInstanceOf(UnifiedMenuBuilder::class, $result);
    }

    public function test_add_field_returns_self(): void
    {
        $result = $this->builder->addField('name', ['prompt' => 'Name:']);

        $this->assertInstanceOf(UnifiedMenuBuilder::class, $result);
    }

    public function test_add_option_returns_self(): void
    {
        $result = $this->builder->addOption('1', 'Option One');

        $this->assertInstanceOf(UnifiedMenuBuilder::class, $result);
    }

    public function test_set_data_provider_returns_self(): void
    {
        $result = $this->builder->setDataProvider(['1' => 'Item']);

        $this->assertInstanceOf(UnifiedMenuBuilder::class, $result);
    }

    public function test_enable_pagination_returns_self(): void
    {
        $result = $this->builder->enablePagination(10);

        $this->assertInstanceOf(UnifiedMenuBuilder::class, $result);
    }

    public function test_enable_search_returns_self(): void
    {
        $result = $this->builder->enableSearch(['name', 'title']);

        $this->assertInstanceOf(UnifiedMenuBuilder::class, $result);
    }

    public function test_enable_validation_returns_self(): void
    {
        $result = $this->builder->enableValidation();

        $this->assertInstanceOf(UnifiedMenuBuilder::class, $result);
    }

    public function test_enable_context_snapshots_returns_self(): void
    {
        $result = $this->builder->enableContextSnapshots();

        $this->assertInstanceOf(UnifiedMenuBuilder::class, $result);
    }

    public function test_enable_analytics_returns_self(): void
    {
        $result = $this->builder->enableAnalytics();

        $this->assertInstanceOf(UnifiedMenuBuilder::class, $result);
    }

    public function test_set_on_complete_returns_self(): void
    {
        $result = $this->builder->setOnComplete(fn () => UssdResponse::end('Done'));

        $this->assertInstanceOf(UnifiedMenuBuilder::class, $result);
    }

    public function test_set_item_formatter_returns_self(): void
    {
        $result = $this->builder->setItemFormatter(fn ($key, $item) => (string) $item);

        $this->assertInstanceOf(UnifiedMenuBuilder::class, $result);
    }

    public function test_set_validator_returns_self(): void
    {
        $result = $this->builder->setValidator(fn () => true);

        $this->assertInstanceOf(UnifiedMenuBuilder::class, $result);
    }

    public function test_add_condition_returns_self(): void
    {
        $result = $this->builder->addCondition(fn () => true, 'menu_name');

        $this->assertInstanceOf(UnifiedMenuBuilder::class, $result);
    }

    public function test_set_config_returns_self(): void
    {
        $result = $this->builder->setConfig(['key' => 'value']);

        $this->assertInstanceOf(UnifiedMenuBuilder::class, $result);
    }

    // ==================== Build returns UssdBuilder ====================

    public function test_build_returns_ussd_builder(): void
    {
        $result = $this->builder->build();

        $this->assertInstanceOf(UssdBuilder::class, $result);
    }

    // ==================== Full fluent chain ====================

    public function test_full_fluent_chain(): void
    {
        $result = $this->builder
            ->setType('paginated')
            ->addOption('1', 'Option')
            ->setDataProvider(['1' => 'Item'])
            ->enablePagination(5)
            ->enableSearch()
            ->enableValidation()
            ->enableContextSnapshots()
            ->setConfig(['custom' => true])
            ->build();

        $this->assertInstanceOf(UssdBuilder::class, $result);
    }

    // ==================== Integration with UssdBuilder ====================

    public function test_unified_menu_via_ussd_builder(): void
    {
        $builder = UssdBuilder::create();

        $builder->unifiedMenu('test', 'Test Menu')
            ->setType('simple')
            ->addOption('1', 'First')
            ->addOption('2', 'Second')
            ->build();

        $framework = $builder->build();

        $this->assertTrue($framework->hasMenu('test'));
    }
}
