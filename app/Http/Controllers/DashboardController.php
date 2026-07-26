<?php

namespace App\Http\Controllers;

use App\DTO\PythonScriptResult;
use App\Exceptions\PythonExecutionException;
use App\Services\Project\ProjectService;
use App\Services\Python\PythonRunnerService;
use Illuminate\Contracts\View\View;

/**
 * Module 1 - Tableau de bord global: project/dataset counts, recent activity,
 * and a Python bridge health check (useful given how much of the pipeline
 * shells out to Python).
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
        [$pythonStatus, $pythonResult] = $this->checkPythonBridge();

        $user = auth()->user();

        return view('dashboard.index', [
            'stats' => $this->projects->dashboardStats($user),
            'recentActivity' => $this->projects->recentActivity($user),
            'pythonStatus' => $pythonStatus,
            'pythonResult' => $pythonResult,
        ]);
    }

    /**
     * @return array{0: 'ok'|'error', 1: PythonScriptResult|string}
     */
    private function checkPythonBridge(): array
    {
        try {
            return ['ok', $this->pythonRunner->run('smoke_test.py', ['ping' => 'dataforge'])];
        } catch (PythonExecutionException $e) {
            return ['error', $e->getMessage()];
        }
    }
}
