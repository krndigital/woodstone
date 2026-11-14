<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Batch expiry per goods-received line (FR-7.8).
        Schema::table('purchase_lines', function (Blueprint $table) {
            $table->date('expires_on')->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::table('purchase_lines', fn (Blueprint $table) => $table->dropColumn('expires_on'));
    }
};
