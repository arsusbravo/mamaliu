<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Raw SQL because doctrine/dbal isn't installed, so Schema::table()->change()
     * isn't available. Gift order lines have no Weekmenu to attach to, so
     * weekmenu_id must become nullable. Purely additive: every existing row
     * already has a non-null value.
     */
    public function up(): void
    {
        DB::statement('ALTER TABLE orders MODIFY weekmenu_id INT NULL DEFAULT NULL');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE orders MODIFY weekmenu_id INT NOT NULL');
    }
};
