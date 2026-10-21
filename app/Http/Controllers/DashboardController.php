<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Criterion;
use App\Models\Evaluation;
use App\Models\Supplier;

class DashboardController extends Controller
{
    public function index()
    {
        $criteria = Criterion::active()->ordered()->get();
        $totalWeight = (float) $criteria->sum('weight');

        $latestEvaluation = Evaluation::with('results.supplier', 'creator')
            ->latest('id')
            ->first();

        $evaluations = Evaluation::with('results')->latest('id')->take(5)->get();

        $stats = [
            'suppliers' => Supplier::count(),
            'active_suppliers' => Supplier::where('is_active', true)->count(),
            'criteria' => $criteria->count(),
            'evaluations' => Evaluation::count(),
            'last_evaluation' => $latestEvaluation?->created_at,
            'winner' => $latestEvaluation?->winner(),
            'winner_margin' => $latestEvaluation && $latestEvaluation->results->count() > 1
                ? $latestEvaluation->results[0]->score - $latestEvaluation->results[1]->score
                : null,
        ];

        $recentActivities = ActivityLog::latest('id')->take(6)->get();

        $methodSplit = Evaluation::selectRaw('method, count(*) as total')->groupBy('method')->pluck('total', 'method');

        $coverage = $criteria->map(function ($criterion) {
            $scored = \App\Models\SupplierScore::where('criterion_id', $criterion->id)
                ->whereHas('supplier')
                ->count();

            return [
                'name' => $criterion->name,
                'scored' => $scored,
                'total' => Supplier::count(),
            ];
        });

        return view('dashboard', compact(
            'stats',
            'criteria',
            'totalWeight',
            'latestEvaluation',
            'evaluations',
            'recentActivities',
            'methodSplit',
            'coverage'
        ));
    }
}
