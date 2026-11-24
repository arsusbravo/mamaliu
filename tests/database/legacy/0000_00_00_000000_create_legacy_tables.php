<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * TEST ONLY. The production database has these tables from before the project
 * used migrations, so there are no migration files for them. This recreates
 * them (columns taken from dump.sql) so the test database can be built from
 * scratch. It runs before every file in database/migrations (see TestCase).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name', 100)->nullable();
            $table->string('username', 100)->unique();
            $table->string('password', 100);
            $table->string('phone', 15)->nullable();
            $table->string('client_token')->nullable();
            $table->string('remember_token')->nullable();
            $table->integer('group_id')->nullable();
            $table->string('usertype', 10);
            $table->boolean('active')->default(true);
            $table->boolean('display_menus')->default(false);
            $table->boolean('cookielogin')->nullable();
            $table->boolean('reregistered')->nullable();
            $table->dateTime('email_verified_at')->nullable();
            $table->dateTime('last_loggedin')->nullable();
            $table->dateTime('created_at')->nullable();
            $table->dateTime('updated_at')->nullable();
        });

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });

        Schema::create('groups', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name', 50)->nullable();
            $table->string('slug', 50)->nullable();
            $table->boolean('invited_this_week')->default(false)->nullable();
            $table->boolean('active')->default(true)->nullable();
            $table->dateTime('created_at')->nullable();
            $table->dateTime('updated_at')->nullable();
        });

        Schema::create('menu', function (Blueprint $table) {
            $table->increments('id');
            $table->string('label', 100)->nullable();
            $table->text('description')->nullable();
            $table->decimal('price', 10, 2)->default(0);
            $table->string('menutype', 20)->nullable();
            $table->string('url')->nullable();
            $table->boolean('published')->default(false);
            $table->integer('ordered')->default(0);
            $table->dateTime('created_at')->nullable();
            $table->dateTime('updated_at')->nullable();
        });

        Schema::create('weekmenu', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('week');
            $table->integer('year');
            $table->integer('group_id')->nullable();
            $table->integer('menu_id');
            $table->integer('quantity')->default(0);
            $table->integer('ordering')->default(0);
            $table->integer('invitation')->default(0);
            $table->date('delivered_on')->nullable();
            $table->string('token', 50)->nullable();
            $table->dateTime('created_at')->nullable();
            $table->dateTime('updated_at')->nullable();
        });

        // weekmenu_id is nullable here because production made it nullable in
        // 2026_10_09_000004 (a MySQL-only ALTER that is skipped on sqlite).
        Schema::create('orders', function (Blueprint $table) {
            $table->increments('id');
            $table->string('order_token', 20)->nullable();
            $table->integer('weekmenu_id')->nullable();
            $table->integer('user_id')->nullable();
            $table->integer('group_id')->nullable();
            $table->integer('quantity')->default(0);
            $table->decimal('special_price', 10, 2)->nullable();
            $table->integer('spicy')->default(0);
            $table->integer('week')->default(0);
            $table->integer('year')->default(0);
            $table->text('notes')->nullable();
            $table->dateTime('created_at')->nullable();
            $table->dateTime('updated_at')->nullable();
        });

        Schema::create('invoices', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('user_id')->nullable();
            $table->string('invoice_number', 50)->nullable();
            $table->dateTime('invoice_date')->nullable();
            $table->dateTime('created_at')->nullable();
            $table->dateTime('updated_at')->nullable();
        });

        Schema::create('invoice_items', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('invoice_id')->nullable();
            $table->integer('order_id')->nullable();
            $table->dateTime('created_at')->nullable();
            $table->dateTime('updated_at')->nullable();
        });

        Schema::create('email_content', function (Blueprint $table) {
            $table->increments('id');
            $table->string('title', 50);
            $table->string('subject', 100);
            $table->string('slug', 50);
            $table->text('content');
            $table->dateTime('created_at')->nullable();
            $table->dateTime('updated_at')->nullable();
        });

        Schema::create('registration_tokens', function (Blueprint $table) {
            $table->increments('id');
            $table->string('token', 225)->nullable();
            $table->date('valid_at')->nullable();
            $table->dateTime('created_at')->nullable();
            $table->dateTime('updated_at')->nullable();
        });
    }
};
