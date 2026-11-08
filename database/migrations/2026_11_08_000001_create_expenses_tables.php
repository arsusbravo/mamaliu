<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Standalone tables: the only foreign key is between the two new tables,
        // so nothing depends on the legacy tables that have no migration files.
        if (! Schema::hasTable('expenses')) {
            Schema::create('expenses', function (Blueprint $table) {
                $table->increments('id');
                $table->string('store')->nullable();
                $table->date('purchased_at');
                $table->decimal('total', 10, 2)->nullable();
                $table->string('receipt_image')->nullable();
                $table->text('notes')->nullable();
                $table->timestamps();

                $table->index('purchased_at');
            });
        }

        if (! Schema::hasTable('expense_items')) {
            Schema::create('expense_items', function (Blueprint $table) {
                $table->increments('id');
                $table->unsignedInteger('expense_id');
                $table->string('name');
                $table->decimal('quantity', 8, 2)->default(1);
                $table->decimal('price', 10, 2);
                $table->string('category')->nullable();
                $table->timestamps();

                $table->foreign('expense_id')->references('id')->on('expenses')->cascadeOnDelete();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('expense_items');
        Schema::dropIfExists('expenses');
    }
};
