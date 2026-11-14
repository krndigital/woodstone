<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('number', 20)->unique();
            $table->string('type', 10); // dine_in | takeaway | delivery
            $table->foreignId('dining_table_id')->nullable()->constrained('dining_tables')->restrictOnDelete();
            $table->unsignedSmallInteger('guests')->default(1);
            $table->string('status', 10)->default('open')->index();
            $table->foreignId('waiter_id')->constrained('users')->restrictOnDelete();
            $table->string('customer_name', 80)->nullable();
            $table->string('customer_phone', 20)->nullable();
            $table->string('delivery_address', 200)->nullable();
            $table->string('note', 200)->nullable();
            $table->decimal('subtotal', 12, 2)->default(0); // cache; always recomputed from items on the server
            $table->string('cancel_reason', 200)->nullable();
            $table->timestamp('opened_at');
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();
            $table->index(['dining_table_id', 'status']);
        });

        Schema::create('kots', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('number')->unique();
            $table->foreignId('order_id')->constrained()->restrictOnDelete();
            $table->foreignId('station_id')->constrained()->restrictOnDelete();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('printed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->restrictOnDelete();
            $table->foreignId('menu_item_id')->constrained()->restrictOnDelete();
            $table->foreignId('station_id')->constrained()->restrictOnDelete();
            $table->foreignId('kot_id')->nullable()->constrained()->restrictOnDelete();
            // Snapshots: later menu price changes never change an order already taken.
            $table->string('name', 100);
            $table->decimal('unit_price', 12, 2);
            $table->json('modifiers')->nullable();
            $table->unsignedSmallInteger('quantity');
            $table->decimal('line_total', 12, 2);
            $table->string('note', 120)->nullable();
            $table->string('status', 10)->default('draft')->index();
            $table->foreignId('added_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('ready_at')->nullable();
            $table->timestamp('served_at')->nullable();
            $table->string('void_reason', 200)->nullable();
            $table->foreignId('voided_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->foreignId('void_approved_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('voided_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_items');
        Schema::dropIfExists('kots');
        Schema::dropIfExists('orders');
    }
};
