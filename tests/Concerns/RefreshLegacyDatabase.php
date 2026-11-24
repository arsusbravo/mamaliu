<?php

namespace Tests\Concerns;

use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * RefreshDatabase, but the schema is built from the legacy table definitions
 * first (those tables have no migration files in production), then every
 * real migration.
 */
trait RefreshLegacyDatabase
{
    use RefreshDatabase {
        migrateFreshUsing as private baseMigrateFreshUsing;
    }

    protected function migrateFreshUsing(): array
    {
        return array_merge($this->baseMigrateFreshUsing(), [
            '--path' => [
                base_path('tests/database/legacy'),
                database_path('migrations'),
            ],
            '--realpath' => true,
        ]);
    }
}
