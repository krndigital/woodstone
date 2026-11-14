<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Staff profile (FR-12.1). Citizenship number is encrypted at rest; attendance PIN is hashed.
        Schema::create('staff', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->unique()->constrained()->nullOnDelete();
            $table->string('name', 80);
            $table->string('phone', 20)->nullable();
            $table->string('address', 150)->nullable();
            $table->text('citizenship_no')->nullable();
            $table->string('position', 40);
            $table->date('joined_on');
            $table->string('salary_type', 10); // monthly | daily
            $table->decimal('salary_amount', 12, 2);
            $table->unsignedTinyInteger('weekly_off')->nullable(); // 0 = Sunday … 6 = Saturday
            $table->time('shift_start')->nullable();
            $table->time('shift_end')->nullable();
            $table->string('attendance_pin')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('attendances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('staff_id')->constrained()->restrictOnDelete();
            $table->date('work_date');
            $table->timestamp('check_in_at')->nullable();
            $table->timestamp('check_out_at')->nullable();
            $table->string('status', 10); // present | late | absent | leave
            $table->string('note', 200)->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['staff_id', 'work_date']);
        });

        Schema::create('leave_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('staff_id')->constrained()->restrictOnDelete();
            $table->date('from_date');
            $table->date('to_date');
            $table->string('reason', 200);
            $table->string('status', 10)->default('pending'); // pending | approved | rejected
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->timestamps();
        });

        Schema::create('staff_advances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('staff_id')->constrained()->restrictOnDelete();
            $table->decimal('amount', 12, 2);
            $table->foreignId('account_id')->constrained()->restrictOnDelete();
            $table->date('given_on');
            $table->string('note', 200)->nullable();
            $table->unsignedBigInteger('payslip_id')->nullable(); // set when recovered from salary
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
        });

        // Monthly salary sheet (FR-12.4). Drafts can be recalculated; paid ones are final.
        Schema::create('payslips', function (Blueprint $table) {
            $table->id();
            $table->foreignId('staff_id')->constrained()->restrictOnDelete();
            $table->string('month', 7); // YYYY-MM
            $table->unsignedSmallInteger('working_days');
            $table->unsignedSmallInteger('present_days');
            $table->unsignedSmallInteger('late_days');
            $table->unsignedSmallInteger('leave_days');
            $table->unsignedSmallInteger('absent_days');
            $table->decimal('base_salary', 12, 2);
            $table->decimal('earned', 12, 2);
            $table->decimal('advance_deduction', 12, 2)->default(0);
            $table->decimal('other_deduction', 12, 2)->default(0);
            $table->decimal('bonus', 12, 2)->default(0);
            $table->decimal('net', 12, 2);
            $table->string('status', 10)->default('draft'); // draft | paid
            $table->foreignId('expense_id')->nullable()->constrained()->restrictOnDelete();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
            $table->unique(['staff_id', 'month']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payslips');
        Schema::dropIfExists('staff_advances');
        Schema::dropIfExists('leave_requests');
        Schema::dropIfExists('attendances');
        Schema::dropIfExists('staff');
    }
};
