<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Customer profile (FR-10.4).
        Schema::table('customers', function (Blueprint $table) {
            $table->string('email', 120)->nullable();
            $table->date('birthday')->nullable();
            $table->date('anniversary')->nullable();
            $table->string('preferred_seat', 40)->nullable();
        });

        // Which customer a bill belongs to (visit history and loyalty, FR-10.5/10.6).
        Schema::table('bills', function (Blueprint $table) {
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
        });

        // Append-only loyalty points ledger (FR-10.6). + earned, − redeemed or expired.
        Schema::create('loyalty_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->restrictOnDelete();
            $table->integer('points');
            $table->string('type', 10); // earn | redeem | expire | adjust | return
            $table->foreignId('bill_id')->nullable()->constrained()->restrictOnDelete();
            $table->date('expires_on')->nullable();
            $table->string('note', 200)->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('created_at');
            $table->index(['customer_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('loyalty_entries');
        Schema::table('bills', function (Blueprint $table) {
            $table->dropConstrainedForeignId('customer_id');
        });
        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn(['email', 'birthday', 'anniversary', 'preferred_seat']);
        });
    }
};
