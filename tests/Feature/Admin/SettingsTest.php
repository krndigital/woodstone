<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\User;
use App\Support\BsDate;
use App\Support\NumberSequence;
use App\Support\Settings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SettingsTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return array_merge(Settings::defaults(), $overrides);
    }

    public function test_vat_and_service_charge_default_to_zero(): void
    {
        $this->assertSame('0', Settings::string('vat_percent'));
        $this->assertSame('0', Settings::string('service_charge_percent'));
        $this->assertSame(10, Settings::get('cashier_discount_limit_percent'));
    }

    public function test_only_super_admin_can_open_or_change_settings(): void
    {
        $this->get(route('admin.settings.edit'))->assertRedirect(route('login'));
        $this->actingAs(User::factory()->admin()->create())->get(route('admin.settings.edit'))->assertForbidden();
        $this->actingAs(User::factory()->admin()->create())->put(route('admin.settings.update'), $this->payload())->assertForbidden();
        $this->actingAs(User::factory()->superAdmin()->create())->get(route('admin.settings.edit'))->assertOk();
    }

    public function test_super_admin_changes_vat_and_it_is_audited(): void
    {
        $owner = User::factory()->superAdmin()->create();

        $this->actingAs($owner)->put(route('admin.settings.update'), $this->payload(['vat_percent' => '13.00', 'pan' => '123456789']))
            ->assertSessionHasNoErrors();

        Settings::flush();
        $this->assertSame('13', Settings::string('vat_percent'));
        $this->assertSame('123456789', Settings::get('pan'));
        $this->assertDatabaseHas('audit_logs', ['action' => 'settings.updated', 'user_id' => $owner->id]);
    }

    public function test_settings_are_validated(): void
    {
        $this->actingAs(User::factory()->superAdmin()->create())
            ->put(route('admin.settings.update'), $this->payload([
                'vat_percent' => '50', 'service_charge_percent' => 'abc', 'pan' => '12',
                'fiscal_year_label' => '2083-84', 'cashier_discount_limit_percent' => 150, 'restaurant_name' => '',
            ]))
            ->assertSessionHasErrors(['vat_percent', 'service_charge_percent', 'pan', 'fiscal_year_label', 'cashier_discount_limit_percent', 'restaurant_name']);
    }

    public function test_bill_numbers_are_sequential_per_fiscal_year(): void
    {
        $fy = BsDate::fiscalYear(now())['label'];
        $this->assertSame($fy.'-00001', NumberSequence::nextBillNumber());
        $this->assertSame($fy.'-00002', NumberSequence::nextBillNumber());

        Settings::save(['fiscal_year_label' => '2084/85'], User::factory()->superAdmin()->create());
        $this->assertSame('2084/85-00001', NumberSequence::nextBillNumber());
    }
}
