<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\File;

abstract class TestCase extends BaseTestCase
{
    private string $testStoragePath;

    /**
     * Every test gets its own throwaway storage folder. The in-memory database
     * always hands out id 1 first, so without this a test could overwrite or
     * delete a real label background or menu photo in storage/app.
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->testStoragePath = sys_get_temp_dir() . '/mamaliu-tests-' . getmypid() . '-' . uniqid();
        File::ensureDirectoryExists($this->testStoragePath . '/app/public');
        File::ensureDirectoryExists($this->testStoragePath . '/app/private');

        $this->app->useStoragePath($this->testStoragePath);
        config([
            'filesystems.disks.local.root' => $this->testStoragePath . '/app/private',
            'filesystems.disks.public.root' => $this->testStoragePath . '/app/public',
        ]);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->testStoragePath);

        parent::tearDown();
    }
}
