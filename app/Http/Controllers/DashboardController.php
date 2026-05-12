<?php

namespace App\Http\Controllers;

use App\Models\ExecutiveOrder;
use App\Models\Ordinance;
use App\Models\Department;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Carbon\Carbon;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        // --- BOUNCER: Kick out read_only users ---
        if (auth()->user()->role === 'read_only') {
            return redirect('/membership');
        }

        // --- 1. GLOBAL FILTERS ---
        $filterYear = $request->input('year', 'all'); 
        $filterActive = $request->input('is_active', 'all'); 
        $filterClass = $request->input('classification', 'all'); // This will now hold the ID

        $applyFilters = function($query, $dateCol) use ($filterYear, $filterActive, $filterClass) {
            if ($filterYear !== 'all') {
                $query->whereYear($dateCol, $filterYear);
            }
            if ($filterActive !== 'all') {
                $query->where('is_active', $filterActive === 'active');
            }
            
            // Smart Classification Filter
            $hasClassification = in_array('classification_id', $query->getModel()->getFillable());
            if ($filterClass !== 'all') {
                if ($hasClassification) {
                    $query->where('classification_id', $filterClass);
                } else {
                    // Ordinances drop out of stats if a classification is selected
                    $query->whereRaw('1 = 0'); 
                }
            }
            return $query;
        };

        // --- 2. CARD STATS ---
        $total_eos = $applyFilters(ExecutiveOrder::query(), 'date_issued')->count();
        $total_ordinances = $applyFilters(Ordinance::query(), 'date_enacted')->count();
        $ords_with_irrs = $applyFilters(Ordinance::has('implementingRules'), 'date_enacted')->count();

        // Merge Active Offices from both EOs and Ordinances
        $active_eo_offices = DB::table('eo_department')
            ->join('executive_orders', 'eo_department.executive_order_id', '=', 'executive_orders.id')
            ->where(function($q) use ($filterYear, $filterActive) {
                if ($filterYear !== 'all') $q->whereYear('executive_orders.date_issued', $filterYear);
                if ($filterActive !== 'all') $q->where('executive_orders.is_active', $filterActive === 'active');
            })
            ->pluck('department_id');

        // 🚀 FIXED: Using ordinance_department table!
        $active_ord_offices = DB::table('ordinance_department') 
            ->join('ordinances', 'ordinance_department.ordinance_id', '=', 'ordinances.id')
            ->where(function($q) use ($filterYear, $filterActive) {
                if ($filterYear !== 'all') $q->whereYear('ordinances.date_enacted', $filterYear);
                if ($filterActive !== 'all') $q->where('ordinances.is_active', $filterActive === 'active');
            })
            ->pluck('department_id');

        $active_offices = $active_eo_offices->concat($active_ord_offices)->unique()->count();

        // --- 3. TREND CHART DATA (Dynamic Ranges) ---
        $trendTime = $request->input('trend_time', 'monthly'); 
        $trendType = $request->input('trend_type', 'all'); 
        $chartYear = ($filterYear === 'all') ? Carbon::now()->year : (int)$filterYear;

        $trendLabels = [];
        $trendData = [];

        if ($trendTime === 'monthly') {
            $trendLabels = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
            $eo_counts = ExecutiveOrder::selectRaw('MONTH(date_issued) as time, COUNT(*) as count')->whereYear('date_issued', $chartYear)->groupBy('time')->pluck('count', 'time')->toArray();
            $ord_counts = Ordinance::selectRaw('MONTH(date_enacted) as time, COUNT(*) as count')->whereYear('date_enacted', $chartYear)->groupBy('time')->pluck('count', 'time')->toArray();
            
            for ($i = 1; $i <= 12; $i++) {
                $val = 0;
                if ($trendType === 'all' || $trendType === 'eo') $val += ($eo_counts[$i] ?? 0);
                if ($trendType === 'all' || $trendType === 'ord') $val += ($ord_counts[$i] ?? 0);
                $trendData[] = $val;
            }
        } elseif ($trendTime === 'weekly') {
            for ($i = 1; $i <= 52; $i++) $trendLabels[] = "Wk $i";
            $eo_counts = ExecutiveOrder::selectRaw('WEEK(date_issued, 1) as time, COUNT(*) as count')->whereYear('date_issued', $chartYear)->groupBy('time')->pluck('count', 'time')->toArray();
            $ord_counts = Ordinance::selectRaw('WEEK(date_enacted, 1) as time, COUNT(*) as count')->whereYear('date_enacted', $chartYear)->groupBy('time')->pluck('count', 'time')->toArray();
            
            for ($i = 1; $i <= 52; $i++) {
                $val = 0;
                if ($trendType === 'all' || $trendType === 'eo') $val += ($eo_counts[$i] ?? 0);
                if ($trendType === 'all' || $trendType === 'ord') $val += ($ord_counts[$i] ?? 0);
                $trendData[] = $val;
            }
        } elseif ($trendTime === 'annual') {
            $currentYear = Carbon::now()->year;
            for ($i = $currentYear - 4; $i <= $currentYear; $i++) $trendLabels[] = (string)$i;
            $eo_counts = ExecutiveOrder::selectRaw('YEAR(date_issued) as time, COUNT(*) as count')->whereBetween(DB::raw('YEAR(date_issued)'), [$currentYear - 4, $currentYear])->groupBy('time')->pluck('count', 'time')->toArray();
            $ord_counts = Ordinance::selectRaw('YEAR(date_enacted) as time, COUNT(*) as count')->whereBetween(DB::raw('YEAR(date_enacted)'), [$currentYear - 4, $currentYear])->groupBy('time')->pluck('count', 'time')->toArray();
            
            foreach ($trendLabels as $yearLabel) {
                $val = 0;
                if ($trendType === 'all' || $trendType === 'eo') $val += ($eo_counts[$yearLabel] ?? 0);
                if ($trendType === 'all' || $trendType === 'ord') $val += ($ord_counts[$yearLabel] ?? 0);
                $trendData[] = $val;
            }
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

        // --- 5. TOP DEPARTMENTS DATA ---
        $deptType = $request->input('dept_type', 'all');

        $departments = Department::withCount([
            'executiveOrders as eo_lead' => fn($q) => $q->where('eo_department.role', 'lead'),
            'executiveOrders as eo_support' => fn($q) => $q->where('eo_department.role', 'support'),
            
            // 🚀 FIXED: Using ordinance_department table!
            'ordinances as ord_lead' => fn($q) => $q->where('ordinance_department.role', 'lead'),
            'ordinances as ord_support' => fn($q) => $q->where('ordinance_department.role', 'support'),
        ])->get()->map(function($dept) use ($deptType) {
            $lead = 0; $support = 0;
            
            if ($deptType === 'all' || $deptType === 'eo') {
                $lead += $dept->eo_lead;
                $support += $dept->eo_support;
            }
            if ($deptType === 'all' || $deptType === 'ord') {
                $lead += $dept->ord_lead ?? 0;
                $support += $dept->ord_support ?? 0;
            }

            $dept->total_involved = $lead + $support;
            $dept->lead_count = $lead;
            $dept->support_count = $support;
            return $dept;
        })->filter(fn($d) => $d->total_involved > 0)
          ->sortByDesc('total_involved')
          ->values();

        // --- 6. AVAILABLE FILTERS ---
        $eo_years = ExecutiveOrder::selectRaw('YEAR(date_issued) as year')->distinct();
        $years = Ordinance::selectRaw('YEAR(date_enacted) as year')->distinct()->union($eo_years)->orderBy('year', 'desc')->pluck('year')->toArray();
        
        $classifications = DB::table('classifications')->orderBy('name')->get();

        return Inertia::render('Dashboard', [
            'stats' => [
                'total_eos' => $total_eos,
                'total_ordinances' => $total_ordinances,
                'ords_with_irrs' => $ords_with_irrs,
                'active_offices' => $active_offices,
            ],
            'trend_chart' => [
                'labels' => $trendLabels,
                'data' => $trendData,
                'year' => $chartYear,
            ],
            'recent_activity' => $recent_activity,
            'top_departments' => $departments,
            'filters' => [
                'year' => $filterYear,
                'is_active' => $filterActive,
                'classification' => $filterClass,
                'trend_time' => $trendTime,
                'trend_type' => $trendType,
                'dept_type' => $deptType,
            ],
            'available_years' => $years,
            'available_classifications' => $classifications 
        ]);
    }
}