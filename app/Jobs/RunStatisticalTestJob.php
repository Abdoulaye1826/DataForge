<?php

namespace App\Jobs;

use App\Models\StatisticalTest;
use App\Services\Analysis\StatisticalTestService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Runs the real statistical_tests.py computation for a StatisticalTest
 * already recorded as Pending (see StatisticalTestService::run).
 */
class RunStatisticalTestJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $timeout = 150;

    public function __construct(public readonly StatisticalTest $test)
    {
    }

    public function handle(StatisticalTestService $service): void
    {
        $service->process($this->test);
    }
}
