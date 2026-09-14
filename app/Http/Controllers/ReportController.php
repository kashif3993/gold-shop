<?php

namespace App\Http\Controllers;

use App\Services\ReportService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;

class ReportController extends Controller
{
    public function __construct(private ReportService $reports) {}

    /** Sales, profit, and current stock valuation — defaults to the current month. */
    public function index(Request $request)
    {
        $from = $request->input('date_from') ?: Carbon::now()->startOfMonth()->toDateString();
        $to = $request->input('date_to') ?: Carbon::now()->toDateString();

        return Inertia::render('reports/index', [
            'filters' => ['date_from' => $from, 'date_to' => $to],
            'sales' => $this->reports->salesSummary($from, $to),
            'profit' => $this->reports->profitSummary($from, $to),
            'stock' => $this->reports->stockValuation(),
            'rateHistory' => $this->reports->rateHistory($from, $to),
        ]);
    }
}
