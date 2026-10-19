<?php

namespace App\Http\Controllers;

use App\Modules\Dashboard\Services\DashboardService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __invoke(Request $request, DashboardService $dashboard): View
    {
        $filters = $request->validate([
            'period' => ['nullable', 'in:today,yesterday,week,month,custom'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
        ]);

        return view('dashboard.index', $dashboard->data($filters, $request->user()));
    }
}
