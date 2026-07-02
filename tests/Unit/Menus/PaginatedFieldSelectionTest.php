<?php

declare(strict_types=1);

namespace Moffhub\Ussd\Tests\Unit\Menus;

use Illuminate\Http\Request;
use Moffhub\Ussd\Builders\FlexibleFormBuilder;
use Moffhub\Ussd\Tests\TestCase;
use Moffhub\Ussd\UssdFramework;
use Moffhub\Ussd\UssdSession;

/**
 * Regression cover for handlePaginatedField()'s selection guard.
 *
 * The guard used to bound the numeric selection against the menu config
 * ($this->config['items_per_page'], default 5) instead of the field's own
 * page size ($field->getItemsPerPage()). When a field set a larger page size
 * than the menu default, valid selections beyond position 5 fell through to
 * "Invalid option" and the field re-rendered, so the form never advanced.
 */
class PaginatedFieldSelectionTest extends TestCase
{
    private UssdSession $session;

    private UssdFramework $framework;

    protected function setUp(): void
    {
        parent::setUp();
        $this->session = new UssdSession('+254712345678', 'test_session');
        $this->framework = new UssdFramework([
            'default_menu' => 'register',
        ]);
        $this->framework->setSession($this->session);
        $this->framework->setRequest(Request::create('/', 'POST', [
            'phoneNumber' => '+254712345678',
            'sessionId' => 'test_session',
        ]));
    }

    /**
     * A field page size larger than the menu default (5): selecting the 7th
     * option must be accepted and advance/complete the form, not re-render.
     */
    public function test_selection_beyond_menu_default_page_size_advances(): void
    {
        $options = [];
        for ($i = 1; $i <= 7; $i++) {
            $options['tier'.$i] = ['id' => 'tier'.$i, 'name' => 'Tier '.$i];
        }

        $builder = new FlexibleFormBuilder('Registration');
        $builder->paginatedField('tier', 'Select tier:', $options, [
            'items_per_page' => 7,
            'item_formatter' => fn ($key, $item): string => $item['name'],
        ]);
        $menu = $builder->build();

        $this->framework->registerMenu('register', $menu);
        $this->session->setCurrentMenu('register');
        $menu->setFramework($this->framework);
        $menu->setConfig($this->framework->getConfig());

        // First render shows the paginated field options.
        $display = $menu->process('', $this->session);
        $this->assertTrue($display->isContinue());
        $this->assertStringContainsString('Select tier:', $display->getMessage());

        // Selecting the 7th option (beyond the menu default of 5) must be accepted.
        $response = $menu->process('7', $this->session);

        $this->assertTrue($response->isEnd(), 'Selecting option 7 should complete the single-field form, not re-render it.');
        $this->assertSame('tier7', $this->session->getFormData('tier'));
    }

    /**
     * A selection within the field page size still works (guards against an
     * over-correction that would reject in-range selections).
     */
    public function test_in_range_selection_still_advances(): void
    {
        $options = [
            'tier1' => ['id' => 'tier1', 'name' => 'Tier 1'],
            'tier2' => ['id' => 'tier2', 'name' => 'Tier 2'],
            'tier3' => ['id' => 'tier3', 'name' => 'Tier 3'],
        ];

        $builder = new FlexibleFormBuilder('Registration');
        $builder->paginatedField('tier', 'Select tier:', $options, [
            'items_per_page' => 3,
            'item_formatter' => fn ($key, $item): string => $item['name'],
        ]);
        $menu = $builder->build();

        $this->framework->registerMenu('register', $menu);
        $this->session->setCurrentMenu('register');
        $menu->setFramework($this->framework);
        $menu->setConfig($this->framework->getConfig());

        $menu->process('', $this->session);
        $response = $menu->process('2', $this->session);

        $this->assertTrue($response->isEnd());
        $this->assertSame('tier2', $this->session->getFormData('tier'));
    }
}
