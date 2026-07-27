<?php

namespace App\Http\Controllers;

use App\Services\Intelligence\IntelligenceFeedService;
use App\Services\Project\ProjectService;
use Illuminate\Contracts\View\View;

/**
 * The Workspace is the landing page after login: quick actions, projects in
 * progress, and the proactive AI intelligence feed. The old stats-first
 * home page moved to AnalyticsController - this page is action-first.
 */
class WorkspaceController extends Controller
{
    public function __construct(
        private readonly ProjectService $projects,
        private readonly IntelligenceFeedService $intelligenceFeed,
    ) {
    }

    public function index(): View
    {
        $user = auth()->user();
        $recentProjects = $this->projects->recentProjects($user);

        return view('workspace.index', [
            'recentProjects' => $recentProjects,
            'continueProject' => $recentProjects->first(),
            'recentActivity' => $this->projects->recentActivity($user, 6),
            'feed' => $this->intelligenceFeed->forUser($user),
            'hasAnyProject' => $recentProjects->isNotEmpty(),
        ]);
    }
}
