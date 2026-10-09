<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gift_qualifying_items', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('gift_id');
            $table->integer('menu_id');
            $table->timestamps();

            $table->unique(['gift_id', 'menu_id']);
            $table->foreign('gift_id')->references('id')->on('gifts')->cascadeOnDelete();
            $table->foreign('menu_id')->references('id')->on('menu')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gift_qualifying_items');
    }
};
