<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('gifts', function (Blueprint $table) {
            // VARCHAR(50), not 255: production's MySQL has a 767-byte index key
            // limit, and a utf8mb4 unique varchar(255) index exceeds it (see the
            // discounts.code migration for the same fix).
            $table->string('code', 50)->unique()->after('name');
        });
    }

    public function down(): void
    {
        Schema::table('gifts', function (Blueprint $table) {
            $table->dropUnique(['code']);
            $table->dropColumn('code');
        });
    }
};
