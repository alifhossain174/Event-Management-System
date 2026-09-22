<?php

namespace App\Http\Controllers;

use App\Http\Requests\GlobalSearchRequest;
use App\Services\GlobalSearchService;
use Illuminate\Contracts\View\View;

final class GlobalSearchController extends Controller
{
    public function __invoke(GlobalSearchRequest $request, GlobalSearchService $search): View
    {
        $term = $search->normalize($request->validated('q', ''));

        return view('search.index', [
            'term' => $term,
            'minimumLength' => $search->minimumLength(),
            'groups' => $search->search($request->user(), $term),
        ]);
    }
}
