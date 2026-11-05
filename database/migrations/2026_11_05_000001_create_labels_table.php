<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Standalone table: no foreign keys, so it doesn't depend on the
        // legacy tables (menu, orders, ...) that have no migration files.
        if (Schema::hasTable('labels')) {
            return;
        }

        Schema::create('labels', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name');
            $table->text('text')->nullable();
            $table->boolean('show_logo')->default(true);
            $table->string('text_color', 7)->default('#000000');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('labels');
    }
};
