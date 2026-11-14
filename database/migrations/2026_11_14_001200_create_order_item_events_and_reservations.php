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
        // Ticket status history: who moved each item and when (FR-3.5).
        Schema::create('order_item_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_item_id')->constrained()->cascadeOnDelete();
            $table->string('status', 10);
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('created_at');
            $table->index(['order_item_id', 'id']);
        });

        // Reservations with advance (FR-1.4) and cabin minimum spend (FR-1.5).
        Schema::create('reservations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dining_table_id')->constrained()->restrictOnDelete();
            $table->string('name', 80);
            $table->string('phone', 20)->nullable();
            $table->dateTime('reserved_at')->index();
            $table->unsignedSmallInteger('guests')->default(2);
            $table->decimal('advance', 12, 2)->default(0);
            $table->decimal('advance_used', 12, 2)->default(0);
            $table->string('status', 10)->default('booked'); // booked | seated | cancelled | no_show
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            $table->string('note', 200)->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
        });
        Schema::table('dining_tables', function (Blueprint $table) {
            $table->decimal('min_spend', 12, 2)->default(0);
        });

        DB::table('gl_accounts')->insert(['code' => '2200', 'name' => 'Customer advances', 'type' => 'liability', 'is_system' => true, 'created_at' => now(), 'updated_at' => now()]);
    }

    public function down(): void
    {
        DB::table('gl_accounts')->where('code', '2200')->delete();
        Schema::table('dining_tables', fn (Blueprint $table) => $table->dropColumn('min_spend'));
        Schema::dropIfExists('reservations');
        Schema::dropIfExists('order_item_events');
    }
};
