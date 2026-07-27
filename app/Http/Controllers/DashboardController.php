<?php

namespace App\Http\Controllers;

use App\Exceptions\PythonExecutionException;
use App\Services\Project\ProjectService;
use App\Services\Python\PythonRunnerService;
use Illuminate\Contracts\View\View;

/**
 * Module 1 - Tableau de bord global: project/dataset counts, recent activity,
 * and an activity trend chart. The Python bridge health check runs silently
 * and only surfaces to the user as an alert if it actually fails.
 */
class DashboardController extends Controller
{
    public function __construct(
        private readonly PythonRunnerService $pythonRunner,
        private readonly ProjectService $projects,
    ) {
    }

    public function index(): View
    {
        $user = auth()->user();

        return view('dashboard.index', [
            'stats' => $this->projects->dashboardStats($user),
            'recentActivity' => $this->projects->recentActivity($user),
            'activityTrend' => $this->projects->activityTrend($user),
            'pythonError' => $this->checkPythonBridge(),
        ]);
    }

    private function checkPythonBridge(): ?string
    {
        try {
            $this->pythonRunner->run('smoke_test.py', ['ping' => 'dataforge']);

            return null;
        } catch (PythonExecutionException $e) {
            return $e->getMessage();
        }
    }
}
