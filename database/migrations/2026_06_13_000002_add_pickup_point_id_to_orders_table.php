<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->unsignedInteger('pickup_point_id')->nullable()->after('group_id');
            $table->foreign('pickup_point_id')->references('id')->on('pickup_points')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropForeign(['pickup_point_id']);
            $table->dropColumn('pickup_point_id');
        });
    }
};
