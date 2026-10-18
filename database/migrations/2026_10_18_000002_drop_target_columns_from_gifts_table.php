<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Gifts no longer have targeting (general/user/group/items) — eligibility
     * is now a per-client member ID check instead. Drop everything except
     * threshold_amount/reward_menu_id/valid_from/valid_until/active.
     */
    public function up(): void
    {
        Schema::table('gifts', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->dropForeign(['group_id']);
            $table->dropUnique(['code']);
        });

        Schema::table('gifts', function (Blueprint $table) {
            $table->dropColumn(['name', 'code', 'description', 'target_type', 'user_id', 'group_id']);
        });
    }

    public function down(): void
    {
        Schema::table('gifts', function (Blueprint $table) {
            $table->string('name', 100)->default('')->after('id');
            $table->string('code', 50)->nullable()->unique()->after('name');
            $table->string('description')->nullable()->after('code');
            $table->string('target_type')->default('general')->after('description');
            $table->integer('user_id')->nullable()->after('target_type');
            $table->integer('group_id')->nullable()->after('user_id');

            $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
            $table->foreign('group_id')->references('id')->on('groups')->nullOnDelete();
        });
    }
};
