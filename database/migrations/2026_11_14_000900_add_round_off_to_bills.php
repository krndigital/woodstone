<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bills', function (Blueprint $table) {
            $table->decimal('round_off', 12, 2)->default(0)->after('vat_amount');
        });
    }

    public function down(): void
    {
        Schema::table('bills', fn (Blueprint $table) => $table->dropColumn('round_off'));
    }
};
