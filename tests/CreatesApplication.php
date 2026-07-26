<?php

namespace Tests;

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Application;

trait CreatesApplication
{
    /**
     * Creates the application.
     */
    public function createApplication(): Application
    {
        $app = require __DIR__.'/../bootstrap/app.php';

        // Redirect storage_path() (datasets, reports, tmp exports, python
        // tmp files) to a dedicated tree so tests never read/write real
        // dev data - the test DB is already separate (dataforge_testing),
        // but the filesystem is shared unless explicitly isolated here too.
        $app->useStoragePath(__DIR__.'/../storage/testing');

        $app->make(Kernel::class)->bootstrap();

        return $app;
    }
}
