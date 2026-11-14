<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        // QR token per seat (FR-5.2, FR-5.4): random, regenerable, never the table id.
        Schema::table('dining_tables', function (Blueprint $table) {
            $table->string('qr_token', 40)->nullable()->unique();
        });
        foreach (DB::table('dining_tables')->pluck('id') as $id) {
            DB::table('dining_tables')->where('id', $id)->update(['qr_token' => Str::random(32)]);
        }

        // Guest calls from the QR page (M16).
        Schema::create('service_calls', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dining_table_id')->constrained()->restrictOnDelete();
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type', 10); // waiter | bill | water | help | ping
            $table->string('note', 120)->nullable();
            $table->string('status', 10)->default('sent'); // sent | accepted | done
            $table->foreignId('accepted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('done_at')->nullable();
            $table->string('ip', 45)->nullable();
            $table->string('user_agent', 250)->nullable();
            $table->string('public_id', 26)->unique(); // what the guest page polls with
            $table->timestamps();
            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_calls');
        Schema::table('dining_tables', fn (Blueprint $table) => $table->dropColumn('qr_token'));
    }
};
