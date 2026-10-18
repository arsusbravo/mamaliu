<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Nullable + unique: most clients won't have one, MySQL allows
            // multiple NULLs in a unique index. varchar(50), not 255 — same
            // 767-byte production index-key lesson as discounts.code/gifts.code.
            $table->string('member_code', 50)->nullable()->unique()->after('phone');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['member_code']);
            $table->dropColumn('member_code');
        });
    }
};
