<?php

declare(strict_types=1);

namespace Moffhub\Ussd\Tests\Unit\Menus;

use Moffhub\Ussd\Builders\FlexibleFormBuilder;
use Moffhub\Ussd\Testing\UssdTester;
use Moffhub\Ussd\Tests\TestCase;

/**
 * Regression: a searchable field must filter by its OWN declared search_fields,
 * not the menu-level config default (['name']).
 *
 * filterData() used to read $this->config['search_fields'] and ignore the
 * $field->getSearchFields() the caller had already computed, so a field
 * declaring search_fields=['sku'] could never be searched by SKU.
 */
class SearchableFieldSearchFieldsTest extends TestCase
{
    private function productForm(): FlexibleFormBuilder
    {
        $builder = new FlexibleFormBuilder('Catalogue');
        $builder->searchableField(
            'product',
            'Pick a product:',
            fn (): array => [
                'p1' => ['id' => 'p1', 'name' => 'Widget', 'sku' => 'ZEBRA-01'],
                'p2' => ['id' => 'p2', 'name' => 'Gadget', 'sku' => 'LION-02'],
            ],
            ['sku'],                 // search by SKU, not name
            ['items_per_page' => 5]
        );

        return $builder;
    }

    public function test_field_is_searched_by_its_own_search_fields(): void
    {
        $tester = UssdTester::fake(['default_menu' => 'catalogue'])
            ->register('catalogue', $this->productForm()->build());

        $tester->dial();          // shows the product field
        $tester->send('98');      // enter search mode
        $tester->send('ZEBRA');   // matches p1 only by its SKU

        $message = $tester->message();
        $this->assertStringNotContainsString('No results found', $message, 'SKU search must match via the field search_fields.');
        $this->assertStringContainsString('Widget', $message);
        $this->assertStringNotContainsString('Gadget', $message);
    }
}
