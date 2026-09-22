<?php

namespace App\Http\Controllers;

use Illuminate\Contracts\View\View;
use Illuminate\Pagination\LengthAwarePaginator;

final class UiStyleGuideController extends Controller
{
    public function __invoke(): View
    {
        return view('style-guide', [
            'samplePagination' => new LengthAwarePaginator(
                items: collect(range(1, 10)),
                total: 42,
                perPage: 10,
                currentPage: 2,
                options: ['path' => route('style-guide')],
            ),
        ]);
    }
}
