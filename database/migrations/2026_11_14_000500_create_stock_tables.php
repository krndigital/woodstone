<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('suppliers', function (Blueprint $table) {
            $table->id();
            $table->string('name', 80)->unique();
            $table->string('phone', 20)->nullable();
            $table->string('pan', 9)->nullable();
            $table->string('address', 150)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // Item master (FR-7.1). Quantities are in the base unit; purchases may use a bigger unit.
        Schema::create('stock_items', function (Blueprint $table) {
            $table->id();
            $table->string('name', 80)->unique();
            $table->string('kind', 12); // ingredient | resale | asset
            $table->string('unit', 12); // base unit: g, ml, pcs, ...
            $table->string('purchase_unit', 12)->nullable(); // kg, carton, crate ...
            $table->decimal('purchase_factor', 12, 3)->nullable(); // base units in one purchase unit
            $table->decimal('on_hand', 14, 3)->default(0); // cache of SUM(stock_movements.quantity)
            $table->decimal('avg_cost', 12, 4)->default(0); // weighted average per base unit (FR-6.4)
            $table->decimal('reorder_level', 14, 3)->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // Append-only stock ledger.
        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stock_item_id')->constrained()->restrictOnDelete();
            $table->string('type', 12); // opening | purchase | sale | wastage | staff_meal | adjustment | count
            $table->decimal('quantity', 14, 3); // + in, − out (base units)
            $table->decimal('unit_cost', 12, 4)->nullable();
            $table->string('reference_type', 20)->nullable(); // purchase | bill
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->string('reason', 200)->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('created_at');
            $table->index(['stock_item_id', 'id']);
            $table->index(['reference_type', 'reference_id']);
        });

        // Recipe per menu item (FR-7.3): base units used per plate.
        Schema::create('recipe_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('menu_item_id')->constrained()->cascadeOnDelete();
            $table->foreignId('stock_item_id')->constrained()->restrictOnDelete();
            $table->decimal('quantity', 12, 3);
            $table->timestamps();
            $table->unique(['menu_item_id', 'stock_item_id']);
        });

        Schema::create('purchase_orders', function (Blueprint $table) {
            $table->id();
            $table->string('number', 20)->unique();
            $table->foreignId('supplier_id')->constrained()->restrictOnDelete();
            $table->string('status', 10)->default('draft'); // draft | sent | partial | received | cancelled
            $table->date('expected_on')->nullable();
            $table->string('note', 200)->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
        });

        Schema::create('purchase_order_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('stock_item_id')->constrained()->restrictOnDelete();
            $table->decimal('quantity', 14, 3); // in purchase unit if the item has one, else base unit
            $table->decimal('expected_rate', 12, 2);
            $table->decimal('received_quantity', 14, 3)->default(0);
            $table->timestamps();
        });

        // Goods received / purchase entry (FR-6.2). Final once saved.
        Schema::create('purchases', function (Blueprint $table) {
            $table->id();
            $table->string('number', 20)->unique();
            $table->foreignId('supplier_id')->constrained()->restrictOnDelete();
            $table->foreignId('purchase_order_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('supplier_bill_no', 40)->nullable();
            $table->date('purchased_on');
            $table->decimal('total', 12, 2);
            $table->decimal('paid_amount', 12, 2)->default(0);
            $table->string('paid_method', 10)->nullable(); // cash | qr | card | bank
            $table->string('note', 200)->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
        });

        Schema::create('purchase_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_id')->constrained()->restrictOnDelete();
            $table->foreignId('stock_item_id')->constrained()->restrictOnDelete();
            $table->decimal('quantity', 14, 3); // as entered
            $table->boolean('in_purchase_unit')->default(false);
            $table->decimal('base_quantity', 14, 3);
            $table->decimal('rate', 12, 2); // per entered unit
            $table->decimal('line_total', 12, 2);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_lines');
        Schema::dropIfExists('purchases');
        Schema::dropIfExists('purchase_order_lines');
        Schema::dropIfExists('purchase_orders');
        Schema::dropIfExists('recipe_lines');
        Schema::dropIfExists('stock_movements');
        Schema::dropIfExists('stock_items');
        Schema::dropIfExists('suppliers');
    }
};
