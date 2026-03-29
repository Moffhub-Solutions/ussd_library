<?php

declare(strict_types=1);

namespace Moffhub\Ussd\Tests\Unit\Builders;

use Moffhub\Ussd\Builders\FlexibleFormBuilder;
use Moffhub\Ussd\Menus\UssdMenu;
use Moffhub\Ussd\Tests\TestCase;
use Moffhub\Ussd\UssdResponse;

class FlexibleFormBuilderTest extends TestCase
{
    // ==================== Fluent API chaining ====================

    public function test_fluent_chaining_all_field_types(): void
    {
        $builder = new FlexibleFormBuilder('Test Form');

        $result = $builder
            ->textField('name', 'Enter name:')
            ->numberField('age', 'Enter age:')
            ->dateField('dob', 'Enter DOB:')
            ->selectField('gender', 'Select gender:', ['M' => 'Male', 'F' => 'Female'])
            ->enableFieldValidation()
            ->enableProgressTracking()
            ->enableContextPreservation();

        $this->assertInstanceOf(FlexibleFormBuilder::class, $result);
    }

    // ==================== Text field ====================

    public function test_text_field_adds_field(): void
    {
        $builder = new FlexibleFormBuilder('Form');
        $builder->textField('name', 'Enter name:', ['optional' => true]);

        $menu = $builder->build();

        $this->assertInstanceOf(UssdMenu::class, $menu);
    }

    // ==================== Number field ====================

    public function test_number_field_adds_field(): void
    {
        $builder = new FlexibleFormBuilder('Form');
        $builder->numberField('age', 'Enter age:');

        $menu = $builder->build();

        $this->assertInstanceOf(UssdMenu::class, $menu);
    }

    // ==================== Date field ====================

    public function test_date_field_adds_field(): void
    {
        $builder = new FlexibleFormBuilder('Form');
        $builder->dateField('birthday', 'Enter birthday:');

        $menu = $builder->build();

        $this->assertInstanceOf(UssdMenu::class, $menu);
    }

    // ==================== Select field ====================

    public function test_select_field_adds_field_with_options(): void
    {
        $builder = new FlexibleFormBuilder('Form');
        $builder->selectField('country', 'Select country:', [
            'KE' => 'Kenya',
            'UG' => 'Uganda',
        ]);

        $menu = $builder->build();

        $this->assertInstanceOf(UssdMenu::class, $menu);
    }

    // ==================== Paginated field ====================

    public function test_paginated_field_adds_field(): void
    {
        $builder = new FlexibleFormBuilder('Form');
        $builder->paginatedField('product', 'Select product:', ['1' => 'Item 1']);

        $menu = $builder->build();

        $this->assertInstanceOf(UssdMenu::class, $menu);
    }

    // ==================== Searchable field ====================

    public function test_searchable_field_adds_field(): void
    {
        $builder = new FlexibleFormBuilder('Form');
        $builder->searchableField('product', 'Search product:', ['1' => 'Item'], ['name']);

        $menu = $builder->build();

        $this->assertInstanceOf(UssdMenu::class, $menu);
    }

    // ==================== Conditional field ====================

    public function test_conditional_field_adds_field(): void
    {
        $builder = new FlexibleFormBuilder('Form');
        $builder->conditionalField('extra', 'Extra info:', fn () => true);

        $menu = $builder->build();

        $this->assertInstanceOf(UssdMenu::class, $menu);
    }

    // ==================== Field validator ====================

    public function test_set_field_validator(): void
    {
        $builder = new FlexibleFormBuilder('Form');
        $builder->textField('email', 'Enter email:');
        $result = $builder->setFieldValidator('email', fn ($input) => str_contains($input, '@') ?: 'Invalid email');

        $this->assertInstanceOf(FlexibleFormBuilder::class, $result);
    }

    public function test_set_field_validator_ignores_nonexistent_field(): void
    {
        $builder = new FlexibleFormBuilder('Form');
        $result = $builder->setFieldValidator('nonexistent', fn () => true);

        $this->assertInstanceOf(FlexibleFormBuilder::class, $result);
    }

    // ==================== Field optional ====================

    public function test_set_field_optional(): void
    {
        $builder = new FlexibleFormBuilder('Form');
        $builder->textField('nickname', 'Nickname:');
        $result = $builder->setFieldOptional('nickname');

        $this->assertInstanceOf(FlexibleFormBuilder::class, $result);
    }

    // ==================== Build output matches expected config ====================

    public function test_build_sets_flexible_form_type(): void
    {
        $builder = new FlexibleFormBuilder('Test');
        $builder->textField('name', 'Name:');

        $menu = $builder->build();

        // Verify it's a UssdMenu with type set
        $reflection = new \ReflectionClass($menu);
        $typeProperty = $reflection->getProperty('type');
        $typeProperty->setAccessible(true);

        $this->assertEquals('flexible_form', $typeProperty->getValue($menu));
    }

    public function test_build_enables_validation_feature(): void
    {
        $builder = new FlexibleFormBuilder('Test');
        $builder->textField('name', 'Name:');
        $builder->enableFieldValidation();

        $menu = $builder->build();

        $this->assertTrue($menu->isFeatureEnabled('validation'));
    }

    public function test_build_enables_context_snapshots_when_preservation_enabled(): void
    {
        $builder = new FlexibleFormBuilder('Test');
        $builder->textField('name', 'Name:');
        $builder->enableContextPreservation();

        $menu = $builder->build();

        $this->assertTrue($menu->isFeatureEnabled('context_snapshots'));
    }

    public function test_build_with_on_complete(): void
    {
        $builder = new FlexibleFormBuilder('Test', function ($session, $formData) {
            return UssdResponse::end('Done');
        });
        $builder->textField('name', 'Name:');

        $menu = $builder->build();

        $this->assertInstanceOf(UssdMenu::class, $menu);
    }

    public function test_build_with_paginated_field_enables_pagination(): void
    {
        $builder = new FlexibleFormBuilder('Test');
        $builder->paginatedField('items', 'Select:', ['1' => 'Item']);

        $menu = $builder->build();

        $this->assertTrue($menu->isFeatureEnabled('pagination'));
    }

    public function test_build_with_searchable_field_enables_search(): void
    {
        $builder = new FlexibleFormBuilder('Test');
        $builder->searchableField('items', 'Search:', ['1' => 'Item']);

        $menu = $builder->build();

        $this->assertTrue($menu->isFeatureEnabled('search'));
    }
}
