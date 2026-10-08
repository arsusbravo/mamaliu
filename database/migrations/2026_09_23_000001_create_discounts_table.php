<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('discounts', function (Blueprint $table) {
            $table->increments('id');
            $table->string('code')->unique();
            $table->string('description')->nullable();

            $table->string('type'); // 'percentage' | 'fixed'
            $table->decimal('value', 10, 2);

            $table->string('target_type'); // 'general' | 'user' | 'group' | 'menu'
            $table->integer('user_id')->nullable();
            $table->integer('group_id')->nullable();
            $table->integer('menu_id')->nullable();

            $table->decimal('min_order_total', 10, 2)->nullable();
            $table->timestamp('valid_from')->nullable();
            $table->timestamp('valid_until')->nullable();
            $table->unsignedInteger('usage_limit')->nullable();
            $table->unsignedInteger('usage_limit_per_user')->nullable();

            $table->boolean('active')->default(true);

            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
            $table->foreign('group_id')->references('id')->on('groups')->nullOnDelete();
            $table->foreign('menu_id')->references('id')->on('menu')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('discounts');
    }
};
