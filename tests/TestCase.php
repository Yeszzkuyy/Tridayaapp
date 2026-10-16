<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Test tidak bergantung pada hasil build Vite: di alur kerja multi-
        // worktree, `public/build` tiap worktree di-symlink ke build `main`,
        // sehingga entry baru (mis. island notification-center) belum tentu
        // ada di manifest saat test dijalankan.
        $this->withoutVite();
    }
}
