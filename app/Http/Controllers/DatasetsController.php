<?php

namespace App\Http\Controllers;

use App\Repositories\Contracts\DatasetRepositoryInterface;
use Illuminate\Contracts\View\View;

/**
 * Global, cross-project view of every dataset the user owns - complements
 * the per-project dataset pages under projects/{project}/datasets/{dataset}.
 */
class DatasetsController extends Controller
{
    public function __construct(private readonly DatasetRepositoryInterface $datasets)
    {
    }

    public function index(): View
    {
        return view('datasets-global.index', [
            'datasets' => $this->datasets->allForUser(auth()->id()),
        ]);
    }
}
