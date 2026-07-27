<?php

namespace App\Http\Controllers;

use App\Repositories\Contracts\ReportRepositoryInterface;
use Illuminate\Contracts\View\View;

/**
 * Global, cross-project view of every report the user has generated -
 * complements the per-project reports pages under
 * projects/{project}/reports.
 */
class ReportsController extends Controller
{
    public function __construct(private readonly ReportRepositoryInterface $reports)
    {
    }

    public function index(): View
    {
        return view('reports-global.index', [
            'reports' => $this->reports->allForUser(auth()->id()),
        ]);
    }
}
