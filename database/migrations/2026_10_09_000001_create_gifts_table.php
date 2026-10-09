<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gifts', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name', 100);
            $table->string('description')->nullable();

            $table->string('target_type'); // 'general' | 'user' | 'group' | 'items'
            $table->integer('user_id')->nullable();
            $table->integer('group_id')->nullable();

            $table->decimal('threshold_amount', 10, 2);
            $table->unsignedInteger('gifts_per_step')->default(1);

            $table->integer('reward_menu_id');

            $table->timestamp('valid_from')->nullable();
            $table->timestamp('valid_until')->nullable();
            $table->boolean('active')->default(true);

            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
            $table->foreign('group_id')->references('id')->on('groups')->nullOnDelete();
            $table->foreign('reward_menu_id')->references('id')->on('menu')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gifts');
    }
};
