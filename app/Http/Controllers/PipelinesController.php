<?php

namespace App\Http\Controllers;

use App\Repositories\Contracts\PipelineStepRepositoryInterface;
use App\Repositories\Contracts\PipelineSuggestionRepositoryInterface;
use Illuminate\Contracts\View\View;

/**
 * Global, cross-project view of pipeline activity: suggestions still
 * awaiting a decision, and a history of steps already applied. Reviewing
 * and accepting/rejecting a suggestion still happens on its dataset page
 * (projects/{project}/datasets/{dataset}) - this page is for visibility
 * and navigation, not a duplicate action surface.
 */
class PipelinesController extends Controller
{
    public function __construct(
        private readonly PipelineSuggestionRepositoryInterface $suggestions,
        private readonly PipelineStepRepositoryInterface $steps,
    ) {
    }

    public function index(): View
    {
        $userId = auth()->id();

        return view('pipelines-global.index', [
            'pendingSuggestions' => $this->suggestions->pendingForUser($userId, 20),
            'recentSteps' => $this->steps->recentForUser($userId, 20),
        ]);
    }
}
