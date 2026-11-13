<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Preparation stations: each item routes its KOT to one (FR-3.6).
        Schema::create('stations', function (Blueprint $table) {
            $table->id();
            $table->string('name', 40)->unique();
            $table->string('kind', 10)->default('kitchen'); // kitchen | bar | counter
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // Categories with one level of subcategories (FR-5.6).
        Schema::create('menu_categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parent_id')->nullable()->constrained('menu_categories')->restrictOnDelete();
            $table->string('name', 60);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['parent_id', 'name']);
        });

        Schema::create('menu_items', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();
            $table->foreignId('category_id')->constrained('menu_categories')->restrictOnDelete();
            $table->foreignId('station_id')->constrained('stations')->restrictOnDelete();
            $table->string('name', 100);
            $table->string('description', 500)->nullable();
            $table->decimal('price', 12, 2);
            // Food cost per plate. Entered by hand until recipes arrive with stock (step 9).
            $table->decimal('cost', 12, 2)->nullable();
            $table->boolean('is_veg')->default(false);
            $table->boolean('vat_applicable')->default(true);
            $table->boolean('show_on_qr')->default(true);
            $table->boolean('is_available')->default(true);  // false = sold out today (FR-3.4)
            $table->boolean('is_active')->default(true);     // false = archived, never hard-deleted
            $table->time('available_from')->nullable();       // time-based availability (FR-5.1)
            $table->time('available_to')->nullable();
            $table->string('image_path')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
            $table->index(['category_id', 'is_active']);
        });

        // Paid extras such as "extra cheese +Rs 50". Order lines will copy name and price at sale time.
        Schema::create('menu_item_modifiers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('menu_item_id')->constrained('menu_items')->cascadeOnDelete();
            $table->string('name', 60);
            $table->decimal('price', 12, 2)->default(0);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('menu_item_modifiers');
        Schema::dropIfExists('menu_items');
        Schema::dropIfExists('menu_categories');
        Schema::dropIfExists('stations');
    }
};
