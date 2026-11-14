<?php

declare(strict_types=1);

namespace Tests\Feature\Staff;

use App\Enums\Role;
use App\Models\Attendance;
use App\Models\Expense;
use App\Models\Payslip;
use App\Models\Staff;
use App\Models\User;
use App\Support\CashBook;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use LogicException;
use Tests\TestCase;

class StaffTest extends TestCase
{
    use RefreshDatabase;

    private function hire(User $admin, array $extra = []): Staff
    {
        $this->actingAs($admin)->post(route('staff.store'), [
            'name' => 'Hari', 'position' => 'Waiter', 'joined_on' => now()->startOfMonth()->subMonth()->toDateString(),
            'salary_type' => 'monthly', 'salary_amount' => '15000', 'attendance_pin' => '1234', 'citizenship_no' => '12-34-56789', ...$extra,
        ])->assertSessionHasNoErrors();

        return Staff::query()->latest('id')->firstOrFail();
    }

    public function test_access_by_role(): void
    {
        $this->get(route('staff.index'))->assertRedirect(route('login'));
        $this->actingAs(User::factory()->role(Role::Cashier)->create())->get(route('staff.index'))->assertForbidden();
        $this->actingAs(User::factory()->role(Role::Waiter)->create())->get(route('attendance.index'))->assertForbidden();
        $this->actingAs(User::factory()->role(Role::Cashier)->create())->get(route('attendance.index'))->assertOk();
        $this->actingAs(User::factory()->admin()->create())->get(route('staff.index'))->assertOk();
        $this->actingAs(User::factory()->admin()->create())->get(route('staff.payroll'))->assertOk();
    }

    public function test_citizenship_is_encrypted_and_pin_hashed(): void
    {
        $staff = $this->hire(User::factory()->admin()->create());

        $raw = DB::table('staff')->where('id', $staff->id)->first();
        $this->assertNotSame('12-34-56789', $raw->citizenship_no);
        $this->assertNotSame('1234', $raw->attendance_pin);
        $this->assertSame('12-34-56789', $staff->citizenship_no);

        $this->actingAs(User::factory()->admin()->create())->get(route('staff.index'))
            ->assertInertia(fn (Assert $page) => $page->where('staff.0.has_citizenship', true)->missing('staff.0.citizenship_no'));
        $this->actingAs(User::factory()->admin()->create())->post(route('staff.store'), ['name' => 'X', 'salary_type' => 'yearly', 'attendance_pin' => '12'])
            ->assertSessionHasErrors(['position', 'joined_on', 'salary_type', 'salary_amount', 'attendance_pin']);
    }

    public function test_punch_in_and_out_with_pin_and_late_mark(): void
    {
        $staff = $this->hire(User::factory()->admin()->create(), ['shift_start' => now()->subHour()->format('H:i')]);
        $cashier = User::factory()->role(Role::Cashier)->create();

        $this->actingAs($cashier)->post(route('attendance.punch'), ['staff_id' => $staff->id, 'pin' => '9999'])->assertSessionHasErrors('pin');
        $this->actingAs($cashier)->post(route('attendance.punch'), ['staff_id' => $staff->id, 'pin' => '1234'])->assertSessionHasNoErrors();
        $row = Attendance::query()->firstOrFail();
        $this->assertSame('late', $row->status);
        $this->assertNull($row->check_out_at);

        $this->actingAs($cashier)->post(route('attendance.punch'), ['staff_id' => $staff->id, 'pin' => '1234'])->assertSessionHasNoErrors();
        $this->assertNotNull($row->refresh()->check_out_at);
        $this->actingAs($cashier)->post(route('attendance.punch'), ['staff_id' => $staff->id, 'pin' => '1234'])->assertSessionHasErrors('pin');
    }

    public function test_salary_sheet_with_absence_advance_and_payment(): void
    {
        $admin = User::factory()->admin()->create();
        $staff = $this->hire($admin);
        $month = now()->subMonth()->format('Y-m');
        $days = (int) now()->subMonth()->daysInMonth;

        // Present every day except two absences.
        for ($d = 1; $d <= $days - 2; $d++) {
            $this->actingAs($admin)->put(route('staff.attendance.mark', $staff), ['work_date' => sprintf('%s-%02d', $month, $d), 'status' => 'present'])->assertSessionHasNoErrors();
        }
        $this->actingAs($admin)->post(route('staff.advances.store', $staff), ['amount' => '2000', 'account_id' => CashBook::account('cash')->id])->assertSessionHasNoErrors();

        $this->actingAs($admin)->post(route('staff.payroll.generate'), ['month' => $month])->assertSessionHasNoErrors();
        $slip = Payslip::query()->firstOrFail();
        $this->assertSame(2, $slip->absent_days);
        $expectedEarned = 1500000 - intdiv(1500000 * 2, $days);
        $this->assertSame(number_format($expectedEarned / 100, 2, '.', ''), $slip->earned);
        $this->assertSame('2000.00', $slip->advance_deduction);

        $this->actingAs($admin)->patch(route('staff.payslips.update', $slip), ['bonus' => '500', 'other_deduction' => '0'])->assertSessionHasNoErrors();
        $this->actingAs($admin)->post(route('staff.payslips.pay', $slip), ['account_id' => CashBook::account('cash')->id])->assertSessionHasNoErrors();

        $slip->refresh();
        $this->assertSame('paid', $slip->status);
        $net = $expectedEarned - 200000 + 50000;
        $this->assertSame(number_format($net / 100, 2, '.', ''), $slip->net);
        $this->assertSame(1, Expense::query()->where('source_type', 'payslip')->count());
        $this->assertSame(-200000 - $net, CashBook::balance(CashBook::account('cash')));
        $this->actingAs($admin)->get(route('staff.payslips.print', $slip))->assertOk()->assertSee('NET PAY');

        // Advance is recovered; regenerating leaves the paid slip alone.
        $this->actingAs($admin)->post(route('staff.payroll.generate'), ['month' => $month]);
        $this->assertSame('paid', $slip->refresh()->status);
        $this->expectException(LogicException::class);
        $slip->forceFill(['net' => '1.00'])->save();
    }

    public function test_approved_leave_is_not_absent(): void
    {
        $admin = User::factory()->admin()->create();
        $staff = $this->hire($admin);
        $month = now()->subMonth()->format('Y-m');
        $this->actingAs($admin)->post(route('staff.leaves.store'), ['staff_id' => $staff->id, 'from_date' => $month.'-01', 'to_date' => $month.'-03', 'reason' => 'Dashain'])->assertSessionHasNoErrors();
        $this->actingAs($admin)->patch(route('staff.leaves.update', 1), ['status' => 'approved'])->assertSessionHasNoErrors();

        $this->actingAs($admin)->post(route('staff.payroll.generate'), ['month' => $month]);
        $slip = Payslip::query()->firstOrFail();
        $this->assertSame(3, $slip->leave_days);
        $this->assertSame($slip->working_days - 3, $slip->absent_days);
    }
}
