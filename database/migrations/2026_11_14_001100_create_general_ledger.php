<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Chart of accounts for the automatic double-entry ledger (FR-13.1).
        Schema::create('gl_accounts', function (Blueprint $table) {
            $table->id();
            $table->string('code', 10)->unique();
            $table->string('name', 80);
            $table->string('type', 10); // asset | liability | equity | income | expense
            $table->boolean('is_system')->default(true);
            $table->timestamps();
        });

        Schema::create('journal_entries', function (Blueprint $table) {
            $table->id();
            $table->date('entry_date')->index();
            $table->string('source_type', 20); // bill | cash | purchase | stock | ... | manual
            $table->unsignedBigInteger('source_id')->nullable();
            $table->string('memo', 200)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at');
            $table->unique(['source_type', 'source_id']);
        });

        Schema::create('journal_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('journal_entry_id')->constrained()->cascadeOnDelete();
            $table->foreignId('gl_account_id')->constrained()->restrictOnDelete();
            $table->decimal('debit', 14, 2)->default(0);
            $table->decimal('credit', 14, 2)->default(0);
            $table->index('gl_account_id');
        });

        // Payables with a due date (FR-15.1) and opening balances on go-live (FR-15.10).
        Schema::table('purchases', function (Blueprint $table) {
            $table->date('due_on')->nullable();
        });
        Schema::table('suppliers', function (Blueprint $table) {
            $table->decimal('opening_balance', 12, 2)->default(0);
            $table->unsignedSmallInteger('credit_days')->default(30);
        });

        $now = now();
        $chart = [
            ['1000', 'Cash in hand', 'asset'], ['1010', 'Bank', 'asset'], ['1020', 'QR / wallet', 'asset'], ['1030', 'Card settlement', 'asset'],
            ['1100', 'Customer receivables', 'asset'], ['1110', 'Staff advances', 'asset'], ['1190', 'Sales clearing', 'asset'], ['1195', 'Transfers in transit', 'asset'],
            ['1200', 'Stock (inventory)', 'asset'], ['1500', 'Fixed assets / capex', 'asset'],
            ['2000', 'Supplier payables', 'liability'], ['2100', 'VAT payable', 'liability'], ['2400', 'Loans', 'liability'],
            ['3000', 'Owner capital', 'equity'], ['3050', 'Opening balances', 'equity'], ['3100', 'Owner drawings', 'equity'],
            ['4000', 'Food & drink sales', 'income'], ['4010', 'Service charge income', 'income'], ['4100', 'Sales returns', 'income'],
            ['4900', 'Rounding', 'income'], ['4950', 'Other income', 'income'],
            ['5000', 'Cost of goods sold', 'expense'], ['5100', 'Wastage, staff meals, stock differences', 'expense'], ['5200', 'Loyalty points used', 'expense'],
            ['6980', 'Cash over / short', 'expense'], ['6990', 'Other expenses', 'expense'],
        ];
        foreach ($chart as [$code, $name, $type]) {
            DB::table('gl_accounts')->insert(['code' => $code, 'name' => $name, 'type' => $type, 'is_system' => true, 'created_at' => $now, 'updated_at' => $now]);
        }
    }

    public function down(): void
    {
        Schema::table('suppliers', fn (Blueprint $table) => $table->dropColumn(['opening_balance', 'credit_days']));
        Schema::table('purchases', fn (Blueprint $table) => $table->dropColumn('due_on'));
        Schema::dropIfExists('journal_lines');
        Schema::dropIfExists('journal_entries');
        Schema::dropIfExists('gl_accounts');
    }
};
