<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('zones', function (Blueprint $table) {
            $table->id();
            $table->string('name', 40)->unique();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('dining_tables', function (Blueprint $table) {
            $table->id();
            $table->foreignId('zone_id')->constrained('zones')->restrictOnDelete();
            $table->string('code', 10)->unique();
            $table->string('kind', 10)->default('table');
            $table->unsignedTinyInteger('seats')->default(4);
            $table->string('status', 10)->default('free')->index();
            $table->string('reserved_for', 80)->nullable();
            $table->timestamp('reserved_at')->nullable();
            $table->timestamp('status_changed_at')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dining_tables');
        Schema::dropIfExists('zones');
    }
};
