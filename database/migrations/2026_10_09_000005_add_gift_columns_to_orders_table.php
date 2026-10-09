<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->integer('menu_id')->nullable()->after('weekmenu_id');
            $table->unsignedInteger('gift_redemption_id')->nullable()->after('discount_amount');

            $table->foreign('menu_id')->references('id')->on('menu')->nullOnDelete();
            $table->foreign('gift_redemption_id')->references('id')->on('gift_redemptions')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropForeign(['menu_id']);
            $table->dropForeign(['gift_redemption_id']);
            $table->dropColumn(['menu_id', 'gift_redemption_id']);
        });
    }
};
