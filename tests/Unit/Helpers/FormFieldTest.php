<?php

declare(strict_types=1);

namespace Moffhub\Ussd\Tests\Unit\Helpers;

use Moffhub\Ussd\Helpers\FormField;
use Moffhub\Ussd\Tests\TestCase;

class FormFieldTest extends TestCase
{
    public function test_it_uses_options_key_for_options(): void
    {
        $options = ['option1' => 'Option 1', 'option2' => 'Option 2'];

        $field = new FormField('test_field', 'Select an option', [
            'type' => 'select',
            'options' => $options,
        ]);

        $this->assertEquals($options, $field->getOptions());
    }

    public function test_it_uses_data_provider_key_as_fallback_for_options(): void
    {
        $dataProvider = ['item1' => 'Item 1', 'item2' => 'Item 2', 'item3' => 'Item 3'];

        $field = new FormField('test_field', 'Select an item', [
            'type' => 'paginated',
            'data_provider' => $dataProvider,
        ]);

        $this->assertEquals($dataProvider, $field->getOptions());
    }

    public function test_options_key_takes_precedence_over_data_provider(): void
    {
        $options = ['opt1' => 'Option 1'];
        $dataProvider = ['dp1' => 'Data Provider 1'];

        $field = new FormField('test_field', 'Select', [
            'type' => 'select',
            'options' => $options,
            'data_provider' => $dataProvider,
        ]);

        $this->assertEquals($options, $field->getOptions());
    }

    public function test_it_returns_null_when_no_options_or_data_provider(): void
    {
        $field = new FormField('test_field', 'Enter text', [
            'type' => 'text',
        ]);

        $this->assertNull($field->getOptions());
    }

    public function test_it_supports_callable_data_provider(): void
    {
        $callableProvider = fn () => ['dynamic1' => 'Dynamic 1', 'dynamic2' => 'Dynamic 2'];

        $field = new FormField('test_field', 'Select dynamic', [
            'type' => 'searchable_paginated',
            'data_provider' => $callableProvider,
        ]);

        $this->assertEquals(['dynamic1' => 'Dynamic 1', 'dynamic2' => 'Dynamic 2'], $field->getOptions());
    }

    public function test_callable_data_provider_receives_session(): void
    {
        $receivedSession = null;
        $callableProvider = function ($session) use (&$receivedSession) {
            $receivedSession = $session;

            return ['item' => 'value'];
        };

        $field = new FormField('test_field', 'Select', [
            'type' => 'paginated',
            'data_provider' => $callableProvider,
        ]);

        $mockSession = new \stdClass;
        $mockSession->id = 'test_session';

        $field->getOptions($mockSession);

        $this->assertSame($mockSession, $receivedSession);
    }

    public function test_paginated_field_is_paginated(): void
    {
        $field = new FormField('test_field', 'Select', [
            'type' => 'paginated',
            'data_provider' => ['a' => 'A', 'b' => 'B'],
        ]);

        $this->assertTrue($field->isPaginated());
        $this->assertFalse($field->isSearchable());
    }

    public function test_searchable_paginated_field_is_paginated_and_searchable(): void
    {
        $field = new FormField('test_field', 'Search and select', [
            'type' => 'searchable_paginated',
            'data_provider' => ['a' => 'A', 'b' => 'B'],
        ]);

        $this->assertTrue($field->isPaginated());
        $this->assertTrue($field->isSearchable());
    }

    public function test_items_per_page_default(): void
    {
        $field = new FormField('test_field', 'Select', [
            'type' => 'paginated',
            'data_provider' => ['a' => 'A'],
        ]);

        $this->assertEquals(5, $field->getItemsPerPage());
    }

    public function test_items_per_page_custom(): void
    {
        $field = new FormField('test_field', 'Select', [
            'type' => 'paginated',
            'data_provider' => ['a' => 'A'],
            'items_per_page' => 10,
        ]);

        $this->assertEquals(10, $field->getItemsPerPage());
    }

    public function test_search_fields_default(): void
    {
        $field = new FormField('test_field', 'Search', [
            'type' => 'searchable_paginated',
            'data_provider' => ['a' => 'A'],
        ]);

        $this->assertEquals(['name'], $field->getSearchFields());
    }

    public function test_search_fields_custom(): void
    {
        $field = new FormField('test_field', 'Search', [
            'type' => 'searchable_paginated',
            'data_provider' => ['a' => 'A'],
            'search_fields' => ['name', 'description', 'code'],
        ]);

        $this->assertEquals(['name', 'description', 'code'], $field->getSearchFields());
    }
}
