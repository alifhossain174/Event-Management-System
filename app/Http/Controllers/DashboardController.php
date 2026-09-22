<?php

namespace App\Http\Controllers;

use App\Http\Requests\DashboardFilterRequest;
use App\Services\DashboardService;
use Illuminate\Contracts\View\View;

final class DashboardController extends Controller
{
    public function __invoke(DashboardFilterRequest $request, DashboardService $dashboard): View
    {
        return view('dashboard.index', $dashboard->for($request->user(), $request->validated()));
    }
}
