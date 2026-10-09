<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gift_redemptions', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('gift_id');
            $table->integer('user_id');
            $table->integer('group_id')->nullable();
            $table->integer('week');
            $table->integer('year');
            $table->decimal('qualifying_total', 10, 2);
            $table->unsignedInteger('gift_quantity');

            $table->timestamps();

            $table->foreign('gift_id')->references('id')->on('gifts')->cascadeOnDelete();
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('group_id')->references('id')->on('groups')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gift_redemptions');
    }
};
