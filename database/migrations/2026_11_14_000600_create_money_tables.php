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
        // Money accounts (FR-9.1). Balances are always the sum of transactions.
        Schema::create('accounts', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique(); // cash | bank | qr | card | custom
            $table->string('name', 60);
            $table->string('kind', 10); // cash | bank | wallet
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // Append-only cash book (FR-9.2). + money in, − money out.
        Schema::create('account_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained()->restrictOnDelete();
            $table->decimal('amount', 12, 2);
            $table->string('source_type', 20); // bill_payment | due_receipt | purchase | supplier_payment | expense | expense_void | salary | advance | manual | transfer | sales_return | day_close
            $table->unsignedBigInteger('source_id')->nullable();
            $table->string('category', 20)->nullable(); // manual: owner_capital | owner_withdrawal | loan | float | other
            $table->string('note', 200)->nullable();
            $table->date('business_date');
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('created_at');
            $table->index(['account_id', 'business_date']);
            $table->index(['source_type', 'source_id']);
        });

        Schema::create('expense_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name', 40)->unique();
            $table->string('kind', 12)->default('operating'); // operating | investment
            $table->string('group', 12)->default('general'); // kitchen | bar | general (for kitchen / bar P&L)
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // Expenses (FR-8). Never deleted: a wrong entry is voided, which adds a reversing cash row.
        Schema::create('expenses', function (Blueprint $table) {
            $table->id();
            $table->string('number', 20)->unique();
            $table->foreignId('expense_category_id')->constrained()->restrictOnDelete();
            $table->string('description', 200);
            $table->decimal('amount', 12, 2);
            $table->foreignId('account_id')->constrained()->restrictOnDelete();
            $table->date('spent_on');
            $table->string('kind', 12); // operating | investment
            $table->string('receipt_path')->nullable();
            $table->string('source_type', 20)->nullable(); // payslip | recurring
            $table->unsignedBigInteger('source_id')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('voided_at')->nullable();
            $table->foreignId('voided_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('void_reason', 200)->nullable();
            $table->timestamps();
            $table->index('spent_on');
        });

        Schema::create('recurring_expenses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('expense_category_id')->constrained()->restrictOnDelete();
            $table->string('description', 200);
            $table->decimal('amount', 12, 2);
            $table->unsignedTinyInteger('day_of_month')->default(1);
            $table->string('last_month', 7)->nullable(); // YYYY-MM last confirmed
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('supplier_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supplier_id')->constrained()->restrictOnDelete();
            $table->decimal('amount', 12, 2);
            $table->foreignId('account_id')->constrained()->restrictOnDelete();
            $table->date('paid_on');
            $table->string('reference', 60)->nullable();
            $table->string('note', 200)->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('created_at');
        });

        Schema::create('purchase_returns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supplier_id')->constrained()->restrictOnDelete();
            $table->foreignId('purchase_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('stock_item_id')->constrained()->restrictOnDelete();
            $table->decimal('quantity', 14, 3); // base units
            $table->decimal('amount', 12, 2); // reduces what we owe the supplier
            $table->string('reason', 200);
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('created_at');
        });

        // Sales return / bill cancel (FR-4.4, FR-4.8). The bill itself never changes.
        Schema::create('sales_returns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bill_id')->constrained()->restrictOnDelete();
            $table->decimal('amount', 12, 2);
            $table->string('refund_method', 10); // cash | qr | card | due
            $table->boolean('restock')->default(false);
            $table->boolean('full_cancel')->default(false);
            $table->string('reason', 200);
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('approved_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('created_at');
        });

        // Day open / close (FR-4.6).
        Schema::create('day_closes', function (Blueprint $table) {
            $table->id();
            $table->date('business_date')->unique();
            $table->decimal('opening_cash', 12, 2);
            $table->foreignId('opened_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('opened_at');
            $table->decimal('expected_cash', 12, 2)->nullable();
            $table->decimal('counted_cash', 12, 2)->nullable();
            $table->decimal('difference', 12, 2)->nullable();
            $table->string('note', 200)->nullable();
            $table->foreignId('closed_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();
        });

        // Takeaway token printed large for pickup (FR-4.7).
        Schema::table('orders', function (Blueprint $table) {
            $table->unsignedSmallInteger('token')->nullable()->after('number');
        });

        $now = now();
        DB::table('accounts')->insert([
            ['code' => 'cash', 'name' => 'Cash in hand', 'kind' => 'cash', 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['code' => 'bank', 'name' => 'Bank account', 'kind' => 'bank', 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['code' => 'qr', 'name' => 'QR / wallet (Fonepay, eSewa)', 'kind' => 'wallet', 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['code' => 'card', 'name' => 'Card settlement', 'kind' => 'bank', 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
        ]);

        $categories = [
            ['Kitchen', 'operating', 'kitchen'], ['Bar', 'operating', 'bar'], ['Gas', 'operating', 'kitchen'],
            ['Utilities', 'operating', 'general'], ['Rent', 'operating', 'general'], ['Salary', 'operating', 'general'],
            ['Repair', 'operating', 'general'], ['Marketing', 'operating', 'general'],
            ['Construction / Capex', 'investment', 'general'], ['Miscellaneous', 'operating', 'general'],
        ];
        foreach ($categories as [$name, $kind, $group]) {
            DB::table('expense_categories')->insert(['name' => $name, 'kind' => $kind, 'group' => $group, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now]);
        }
    }

    public function down(): void
    {
        Schema::table('orders', fn (Blueprint $table) => $table->dropColumn('token'));
        Schema::dropIfExists('day_closes');
        Schema::dropIfExists('sales_returns');
        Schema::dropIfExists('purchase_returns');
        Schema::dropIfExists('supplier_payments');
        Schema::dropIfExists('recurring_expenses');
        Schema::dropIfExists('expenses');
        Schema::dropIfExists('expense_categories');
        Schema::dropIfExists('account_transactions');
        Schema::dropIfExists('accounts');
    }
};
