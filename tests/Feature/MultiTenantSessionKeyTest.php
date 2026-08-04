<?php

declare(strict_types=1);

namespace Moffhub\Ussd\Tests\Feature;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Moffhub\Ussd\Actions\NavigateAction;
use Moffhub\Ussd\Builders\UssdBuilder;
use Moffhub\Ussd\Tests\TestCase;
use Moffhub\Ussd\UssdFramework;
use Moffhub\Ussd\UssdSession;

/**
 * Live session state is keyed by phone number by default, which is wrong the
 * moment several tenants share one short code: the same subscriber dialling
 * two tenants would resume whichever session they touched last. Hosts pass an
 * explicit `session_key` to namespace the state instead.
 */
class MultiTenantSessionKeyTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    public function test_state_is_keyed_by_phone_number_when_no_session_key_is_given(): void
    {
        $session = new UssdSession('+254712345678', 'sess_default');
        $session->setCurrentMenu('main');
        $session->save();

        $this->assertNotNull(Cache::get('ussd_session_+254712345678'));
    }

    public function test_an_explicit_session_key_namespaces_the_state(): void
    {
        $session = new UssdSession('+254712345678', 'sess_a', ['session_key' => '7:sess_a']);
        $session->setCurrentMenu('balance');
        $session->save();

        $this->assertNotNull(Cache::get('ussd_session_7:sess_a'));
        $this->assertNull(Cache::get('ussd_session_+254712345678'));
    }

    public function test_two_tenants_do_not_share_a_session_for_the_same_subscriber(): void
    {
        $first = new UssdSession('+254712345678', 'sess_a', ['session_key' => '1:sess_a']);
        $first->setCurrentMenu('sacco_loans');
        $first->setFormData('amount', '500');
        $first->save();

        $second = new UssdSession('+254712345678', 'sess_b', ['session_key' => '2:sess_b']);

        $this->assertNotEquals('sacco_loans', $second->getCurrentMenu());
        $this->assertNull($second->getFormData('amount'));

        $second->setCurrentMenu('insurance_quote');
        $second->save();

        $reloadedFirst = new UssdSession('+254712345678', 'sess_a', ['session_key' => '1:sess_a']);

        $this->assertEquals('sacco_loans', $reloadedFirst->getCurrentMenu());
        $this->assertEquals('500', $reloadedFirst->getFormData('amount'));
    }

    public function test_the_framework_resumes_the_session_matching_its_session_key(): void
    {
        $tenantOne = $this->frameworkFor('1:sess_a', 'Tenant one');
        $tenantTwo = $this->frameworkFor('2:sess_b', 'Tenant two');

        $firstReply = $tenantOne->handle($this->leg('sess_a', ''));
        $this->assertStringContainsString('Tenant one', $firstReply->getMessage());

        // Same subscriber, different slice: must start fresh on tenant two.
        $secondReply = $tenantTwo->handle($this->leg('sess_b', ''));
        $this->assertStringContainsString('Tenant two', $secondReply->getMessage());

        // And tenant one's session must still be its own: without the explicit
        // key, tenant two's newer state would be resumed here instead.
        $resumed = $tenantOne->handle($this->leg('sess_a', '1'));
        $this->assertStringContainsString('Tenant one inner', $resumed->getMessage());
    }

    public function test_saving_a_session_does_not_touch_the_database_when_it_is_disabled(): void
    {
        // The table does not exist in this test app. Without the config guard
        // this write is attempted on every save, and only swallowed, which on
        // Postgres poisons whatever transaction the host is inside.
        $session = new UssdSession('+254712345678', 'sess_nodb', [
            'session_key' => '9:sess_nodb',
            'database' => ['enabled' => false],
        ]);

        $session->setCurrentMenu('main');
        $session->save();

        $this->assertFalse(Schema::hasTable('ussd_user_sessions'));
        $this->assertNotNull(Cache::get('ussd_session_9:sess_nodb'));
    }

    private function frameworkFor(string $sessionKey, string $title): UssdFramework
    {
        return UssdBuilder::create([
            'session_key' => $sessionKey,
            'analytics' => ['enabled' => false],
            'database' => ['enabled' => false],
        ])
            ->simpleMenu('main', $title, ['1' => 'Details'], ['1' => new NavigateAction('details')])
            ->simpleMenu('details', $title.' inner', [])
            ->build();
    }

    private function leg(string $sessionId, string $text): Request
    {
        return Request::create('/ussd', 'POST', [
            'sessionId' => $sessionId,
            'phoneNumber' => '+254712345678',
            'serviceCode' => '*483*03#',
            'text' => $text,
        ]);
    }
}
