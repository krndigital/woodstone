<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_groups', function (Blueprint $table) {
            $table->id();
            $table->string('name', 60)->unique();
            $table->timestamps();
        });

        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->string('name', 80);
            $table->string('phone', 20)->nullable()->unique();
            $table->foreignId('customer_group_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('credit_limit', 12, 2);
            $table->string('note', 200)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // Bills are final once created (FR-4.4): numbers per fiscal year, never deleted.
        Schema::create('bills', function (Blueprint $table) {
            $table->id();
            $table->string('number', 20)->unique();
            $table->foreignId('order_id')->unique()->constrained()->restrictOnDelete();
            $table->decimal('subtotal', 12, 2);
            $table->string('discount_type', 10)->nullable(); // percent | amount
            $table->decimal('discount_value', 12, 2)->default(0);
            $table->decimal('discount_amount', 12, 2)->default(0);
            $table->string('discount_reason', 200)->nullable();
            $table->foreignId('discount_approved_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->decimal('service_percent', 5, 2);
            $table->decimal('service_amount', 12, 2);
            $table->decimal('vat_percent', 5, 2);
            $table->decimal('vat_amount', 12, 2);
            $table->decimal('total', 12, 2);
            $table->decimal('paid_amount', 12, 2);
            $table->decimal('due_amount', 12, 2);
            $table->foreignId('cashier_id')->constrained('users')->restrictOnDelete();
            $table->unsignedSmallInteger('print_count')->default(0);
            $table->timestamps();
            $table->index('created_at');
        });

        // Append-only.
        Schema::create('bill_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bill_id')->constrained()->restrictOnDelete();
            $table->string('payer', 40)->nullable(); // "Person 2" in a group split (D-07)
            $table->string('method', 10); // cash | qr | card | due
            $table->decimal('amount', 12, 2);
            $table->foreignId('customer_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('reference', 60)->nullable(); // QR / card transaction id
            $table->boolean('qr_checked')->default(false);
            $table->foreignId('received_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('created_at');
        });

        // Customer dues ledger. Append-only: + means the customer owes more, − means less.
        Schema::create('ledger_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->restrictOnDelete();
            $table->string('type', 15); // bill_due | payment | transfer_in | transfer_out
            $table->decimal('amount', 12, 2);
            $table->foreignId('bill_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('related_customer_id')->nullable()->constrained('customers')->restrictOnDelete();
            $table->string('method', 10)->nullable(); // for payments: cash | qr | card
            $table->string('reference', 60)->nullable();
            $table->string('note', 200)->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('created_at');
            $table->index(['customer_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ledger_entries');
        Schema::dropIfExists('bill_payments');
        Schema::dropIfExists('bills');
        Schema::dropIfExists('customers');
        Schema::dropIfExists('customer_groups');
    }
};
