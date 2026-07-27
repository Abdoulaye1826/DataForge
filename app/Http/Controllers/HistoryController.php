<?php

namespace App\Http\Controllers;

use App\Repositories\Contracts\ActivityLogRepositoryInterface;
use Illuminate\Contracts\View\View;

/**
 * Full, paginated activity history across every project the user owns -
 * the Workspace only shows the last handful of entries, this is the
 * complete log.
 */
class HistoryController extends Controller
{
    public function __construct(private readonly ActivityLogRepositoryInterface $activityLogs)
    {
    }

    public function index(): View
    {
        return view('history.index', [
            'activity' => $this->activityLogs->paginatedForUser(auth()->id()),
        ]);
    }
}
