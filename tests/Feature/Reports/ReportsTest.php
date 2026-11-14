<?php

declare(strict_types=1);

namespace Tests\Feature\Reports;

use App\Enums\Role;
use App\Models\DiningTable;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\User;
use App\Reports\ReportCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;
use ZipArchive;

class ReportsTest extends TestCase
{
    use RefreshDatabase;

    private function sale(string $price = '500.00', int $qty = 2): void
    {
        $waiter = User::factory()->role(Role::Waiter)->create();
        $this->actingAs($waiter)->post(route('pos.orders.store'), ['type' => 'dine_in', 'dining_table_id' => DiningTable::factory()->create()->id, 'guests' => 3]);
        $order = Order::query()->latest('id')->firstOrFail();
        $this->actingAs($waiter)->post(route('pos.orders.items.store', $order), ['menu_item_id' => MenuItem::factory()->create(['price' => $price, 'name' => '=Momo'])->id, 'quantity' => $qty]);
        $this->actingAs($waiter)->post(route('pos.orders.kot', $order));
        $total = number_format((float) $price * $qty, 2, '.', '');
        $this->actingAs(User::factory()->role(Role::Cashier)->create())->post(route('billing.settle', $order), ['payments' => [['method' => 'cash', 'amount' => $total]]])->assertSessionHasNoErrors();
    }

    public function test_access_by_role(): void
    {
        $this->get(route('reports.index'))->assertRedirect(route('login'));
        $this->actingAs(User::factory()->role(Role::Cashier)->create())->get(route('reports.index'))->assertForbidden();
        $this->actingAs(User::factory()->admin()->create())->get(route('reports.show', 'daily-sales'))->assertOk();
        // Owner-only reports.
        $this->actingAs(User::factory()->admin()->create())->get(route('reports.show', 'profit-loss'))->assertForbidden();
        $this->actingAs(User::factory()->superAdmin()->create())->get(route('reports.show', 'profit-loss'))->assertOk();
        $this->actingAs(User::factory()->admin()->create())->get(route('reports.show', 'nope'))->assertNotFound();
    }

    public function test_every_report_runs_on_empty_and_real_data(): void
    {
        $owner = User::factory()->superAdmin()->create();
        foreach (array_keys(ReportCatalog::all()) as $key) {
            $this->actingAs($owner)->get(route('reports.show', $key))->assertOk();
        }
        $this->sale();
        foreach (array_keys(ReportCatalog::all()) as $key) {
            $this->actingAs($owner)->get(route('reports.show', ['report' => $key, 'from' => now()->subDays(7)->toDateString(), 'to' => now()->toDateString()]))->assertOk();
        }
    }

    public function test_daily_sales_and_items_figures(): void
    {
        $this->sale('500.00', 2);
        $this->sale('250.00', 1);
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->get(route('reports.show', 'daily-sales'))
            ->assertInertia(fn (Assert $page) => $page->component('reports/Show')->where('data.totals.bills', 2)->where('data.totals.total', 125000)->where('data.totals.cash', 125000)->where('data.totals.net', 125000));
        $this->actingAs($admin)->get(route('reports.show', 'items'))
            ->assertInertia(fn (Assert $page) => $page->where('data.rows.0.qty', 2)->where('data.totals.revenue', 125000));
        $this->actingAs($admin)->get(route('reports.show', 'tables'))
            ->assertInertia(fn (Assert $page) => $page->where('data.totals.covers', 6));
    }

    public function test_excel_and_pdf_export(): void
    {
        $this->sale();
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)->get(route('reports.excel', 'items'));
        $response->assertOk()->assertDownload();
        $file = $response->baseResponse->getFile()->getPathname();
        $zip = new ZipArchive;
        $this->assertTrue($zip->open($file));
        $sheet = (string) $zip->getFromName('xl/worksheets/sheet1.xml');
        $this->assertStringContainsString('Food cost', $sheet);
        $this->assertStringContainsString('&apos;=Momo', $sheet); // formula injection blocked
        $this->assertStringContainsString('<v>1000</v>', $sheet);
        $zip->close();

        $this->actingAs($admin)->get(route('reports.pdf', 'daily-sales'))->assertOk()->assertSee('Daily sales')->assertSee('1,000.00');
        $this->assertDatabaseHas('audit_logs', ['action' => 'report.exported']);
        $this->actingAs($admin)->get(route('reports.excel', 'audit'))->assertForbidden();
    }

    public function test_owner_dashboard_figures_and_plain_dashboard_for_staff(): void
    {
        $this->sale();
        $this->actingAs(User::factory()->superAdmin()->create())->get(route('dashboard'))
            ->assertInertia(fn (Assert $page) => $page->component('Dashboard')->where('owner.kpis.sales', 100000)->where('owner.kpis.bills', 1)->has('owner.topItems', 1)->where('owner.mix.cash', 100000));
        $this->actingAs(User::factory()->superAdmin()->create())->get(route('dashboard', ['range' => 'month']))->assertInertia(fn (Assert $page) => $page->where('owner.range', 'month'));
        $this->actingAs(User::factory()->role(Role::Waiter)->create())->get(route('dashboard'))->assertInertia(fn (Assert $page) => $page->where('owner', null));
    }
}
