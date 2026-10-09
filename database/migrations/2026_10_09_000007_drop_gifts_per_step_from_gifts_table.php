<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Gifts are always 1 per threshold step (floor(total / threshold) gifts) —
     * the configurable multiplier caused confusion (e.g. a rule accidentally
     * set to 100 granted 100 free items for a single qualifying step).
     */
    public function up(): void
    {
        Schema::table('gifts', function (Blueprint $table) {
            $table->dropColumn('gifts_per_step');
        });
    }

    public function down(): void
    {
        Schema::table('gifts', function (Blueprint $table) {
            $table->unsignedInteger('gifts_per_step')->default(1)->after('threshold_amount');
        });
    }
};
