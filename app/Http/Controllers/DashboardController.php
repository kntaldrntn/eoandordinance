<?php

namespace App\Http\Controllers;

use App\Models\ExecutiveOrder;
use App\Models\Ordinance;
use App\Models\ImplementingRuleandRegulation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Carbon\Carbon;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        // --- 1. GET FILTERS ---
        $filterYear = $request->input('year', 'all'); // Default to 'all' or Carbon::now()->year
        $filterActive = $request->input('is_active', 'all'); // 'all', 'active', 'inactive'

        // Helper to apply filters
        $applyFilters = function($query, $dateCol) use ($filterYear, $filterActive) {
            if ($filterYear !== 'all') {
                $query->whereYear($dateCol, $filterYear);
            }
            if ($filterActive !== 'all') {
                $query->where('is_active', $filterActive === 'active' ? true : false);
            }
            return $query;
        };

        // --- 2. CARD STATS ---
        
        // Total Counts (Filtered)
        $total_eos = $applyFilters(ExecutiveOrder::query(), 'date_issued')->count();
        $total_ordinances = $applyFilters(Ordinance::query(), 'date_enacted')->count();
        $total_filtered = $total_eos + $total_ordinances;

        // Pending IRRs (Count only "Drafting" or "Pending")
        $pending_irrs = ImplementingRuleandRegulation::whereIn('status', ['Drafting', 'Pending Approval'])->count();

        // Active Offices (Count unique offices involved in the filtered result)
        $active_offices = DB::table('eo_department')
            ->join('executive_orders', 'eo_department.executive_order_id', '=', 'executive_orders.id')
            ->where('role', 'lead')
            ->where(function($q) use ($filterYear, $filterActive) {
                if ($filterYear !== 'all') $q->whereYear('executive_orders.date_issued', $filterYear);
                if ($filterActive !== 'all') $q->where('executive_orders.is_active', $filterActive === 'active');
            })
            ->select('department_id')
            ->union(
                DB::table('ordinance_department')
                ->join('ordinances', 'ordinance_department.ordinance_id', '=', 'ordinances.id')
                ->where('role', 'sponsor')
                ->where(function($q) use ($filterYear, $filterActive) {
                    if ($filterYear !== 'all') $q->whereYear('ordinances.date_enacted', $filterYear);
                    if ($filterActive !== 'all') $q->where('ordinances.is_active', $filterActive === 'active');
                })
                ->select('department_id')
            )
            ->count();

        // --- 3. CHART DATA ---
        // If 'all' selected, use current year for chart to keep it readable
        $chartYear = ($filterYear === 'all') ? Carbon::now()->year : $filterYear;

        $getMonthly = function($model, $dateCol) use ($chartYear, $filterActive) {
            $q = $model::select(DB::raw('COUNT(id) as count'), DB::raw('MONTH('.$dateCol.') as month'))
                ->whereYear($dateCol, $chartYear);
            
            if ($filterActive !== 'all') {
                $q->where('is_active', $filterActive === 'active');
            }
            return $q->groupBy('month')->pluck('count', 'month');
        };

        $eo_monthly = $getMonthly(ExecutiveOrder::class, 'date_issued');
        $ord_monthly = $getMonthly(Ordinance::class, 'date_enacted');

        $chart_data = [];
        for ($i = 1; $i <= 12; $i++) {
            $chart_data[] = ($eo_monthly[$i] ?? 0) + ($ord_monthly[$i] ?? 0);
        }

        // --- 4. RECENT ACTIVITY ---
        $latest_eos = $applyFilters(ExecutiveOrder::with('status'), 'date_issued')
            ->latest('updated_at')
            ->take(5)->get()
            ->map(fn($item) => [
                'id' => $item->id,
                'number' => $item->eo_number,
                'title' => $item->title,
                'status' => $item->status ? $item->status->name : 'Unknown',
                'type' => 'EO',
                'date_raw' => $item->updated_at,
                'date' => Carbon::parse($item->updated_at)->diffForHumans(),
            ]);

        $latest_ords = $applyFilters(Ordinance::with('status'), 'date_enacted')
            ->latest('updated_at')
            ->take(5)->get()
            ->map(fn($item) => [
                'id' => $item->id,
                'number' => $item->ordinance_number,
                'title' => $item->title,
                'status' => $item->status ? $item->status->name : 'Unknown',
                'type' => 'ORD',
                'date_raw' => $item->updated_at,
                'date' => Carbon::parse($item->updated_at)->diffForHumans(),
            ]);

        $recent_activity = $latest_eos->concat($latest_ords)
            ->sortByDesc('date_raw')
            ->take(5)
            ->values();

        // --- 5. AVAILABLE YEARS ---
        $eo_years = ExecutiveOrder::selectRaw('YEAR(date_issued) as year')->distinct();
        $years = Ordinance::selectRaw('YEAR(date_enacted) as year')
            ->distinct()
            ->union($eo_years)
            ->orderBy('year', 'desc')
            ->pluck('year')
            ->toArray();

        return Inertia::render('Dashboard', [
            'stats' => [
                'total_eos' => $total_eos,
                'total_ordinances' => $total_ordinances,
                'issued_this_year' => $total_filtered,
                'pending_irrs' => $pending_irrs,
                'active_offices' => $active_offices,
            ],
            'chart' => [
                'data' => $chart_data,
                'year' => (int)$chartYear,
            ],
            'recent_activity' => $recent_activity,
            'filters' => [
                'year' => $filterYear,
                'is_active' => $filterActive,
            ],
            'available_years' => $years
        ]);
    }
}