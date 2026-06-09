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
        if (auth()->user()->role === 'read_only') {
            return redirect('/membership');
        }

        // --- 1. TOP LEVEL STATS ---
        $total_eos = ExecutiveOrder::count();
        $total_ordinances = Ordinance::count();
        $ords_with_irrs = Ordinance::has('implementingRules')->count();

        // --- 2. FETCH AVAILABLE FILTER OPTIONS ---
        $currentYear = Carbon::now()->year;
        $eo_years = ExecutiveOrder::selectRaw('YEAR(date_issued) as year')->distinct()->pluck('year');
        $ord_years = Ordinance::selectRaw('YEAR(date_enacted) as year')->distinct()->pluck('year');
        $available_years = $eo_years->concat($ord_years)->unique()->sortDesc()->values()->toArray();
        if (empty($available_years)) $available_years = [$currentYear];

        $statuses = DB::table('statuses')->orderBy('id')->get();
        $classifications = DB::table('classifications')->orderBy('name')->get();

        // ==========================================
        // 3. EXECUTIVE ORDERS ANALYTICS
        // ==========================================
        $eoYear = $request->input('eo_year', $currentYear);
        $eoClass = $request->input('eo_class', 'all');
        $eoStatus = $request->input('eo_status', 'all');
        $eoActive = $request->input('eo_active', 'all');
        $eoTrendTime = $request->input('eo_trend_time', 'monthly');

        $eoQuery = ExecutiveOrder::query();
        if ($eoYear !== 'all') $eoQuery->whereYear('date_issued', $eoYear);
        if ($eoClass !== 'all') $eoQuery->where('classification_id', $eoClass);
        if ($eoStatus !== 'all') $eoQuery->where('status_id', $eoStatus);
        if ($eoActive !== 'all') $eoQuery->where('is_active', $eoActive === 'active' ? 1 : 0);

        // Dynamic EO Trend Data
        $eoTrendLabels = [];
        $eoTrendData = [];

        if ($eoTrendTime === 'monthly') {
            $eoTrendLabels = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
            $eoTrendRaw = (clone $eoQuery)->selectRaw('MONTH(date_issued) as m, COUNT(*) as c')->groupBy('m')->pluck('c', 'm');
            for($i=1; $i<=12; $i++) $eoTrendData[] = $eoTrendRaw[$i] ?? 0;
        } elseif ($eoTrendTime === 'weekly') {
            for ($i = 1; $i <= 52; $i++) $eoTrendLabels[] = "Wk $i";
            $eoTrendRaw = (clone $eoQuery)->selectRaw('WEEK(date_issued, 1) as w, COUNT(*) as c')->groupBy('w')->pluck('c', 'w');
            for($i=1; $i<=52; $i++) $eoTrendData[] = $eoTrendRaw[$i] ?? 0;
        } elseif ($eoTrendTime === 'annual') {
            for ($i = $currentYear - 4; $i <= $currentYear; $i++) $eoTrendLabels[] = (string)$i;
            
            $eoAnnualQuery = ExecutiveOrder::query();
            if ($eoClass !== 'all') $eoAnnualQuery->where('classification_id', $eoClass);
            if ($eoStatus !== 'all') $eoAnnualQuery->where('status_id', $eoStatus);
            if ($eoActive !== 'all') $eoAnnualQuery->where('is_active', $eoActive === 'active' ? 1 : 0);
            
            $eoTrendRaw = $eoAnnualQuery->selectRaw('YEAR(date_issued) as y, COUNT(*) as c')->whereBetween(DB::raw('YEAR(date_issued)'), [$currentYear - 4, $currentYear])->groupBy('y')->pluck('c', 'y');
            foreach($eoTrendLabels as $yearLabel) $eoTrendData[] = $eoTrendRaw[$yearLabel] ?? 0;
        }

        // EO Classification Data
        $eoClassRaw = (clone $eoQuery)->selectRaw('classification_id, COUNT(*) as c')->groupBy('classification_id')->pluck('c', 'classification_id');
        $eoClassLabels = []; $eoClassData = [];
        foreach($classifications as $c) {
            if(($eoClassRaw[$c->id] ?? 0) > 0) {
                $eoClassLabels[] = $c->name;
                $eoClassData[] = $eoClassRaw[$c->id];
            }
        }

        // EO Status Data
        $eoStatusRaw = (clone $eoQuery)->selectRaw('status_id, COUNT(*) as c')->groupBy('status_id')->pluck('c', 'status_id');
        $eoStatusLabels = []; $eoStatusData = [];
        foreach($statuses as $s) {
            if(($eoStatusRaw[$s->id] ?? 0) > 0) {
                $eoStatusLabels[] = $s->name;
                $eoStatusData[] = $eoStatusRaw[$s->id];
            }
        }

        // ==========================================
        // 4. ORDINANCES ANALYTICS
        // ==========================================
        $ordYear = $request->input('ord_year', $currentYear);
        $ordStatus = $request->input('ord_status', 'all');
        $ordIrr = $request->input('ord_irr', 'all');
        $ordActive = $request->input('ord_active', 'all');
        $ordTrendTime = $request->input('ord_trend_time', 'monthly');

        $ordQuery = Ordinance::query();
        if ($ordYear !== 'all') $ordQuery->whereYear('date_enacted', $ordYear);
        if ($ordStatus !== 'all') $ordQuery->where('status_id', $ordStatus);
        if ($ordActive !== 'all') $ordQuery->where('is_active', $ordActive === 'active' ? 1 : 0);
        if ($ordIrr === 'with') $ordQuery->has('implementingRules');
        if ($ordIrr === 'without') $ordQuery->doesntHave('implementingRules');

        // Dynamic Ord Trend Data
        $ordTrendLabels = [];
        $ordTrendData = [];

        if ($ordTrendTime === 'monthly') {
            $ordTrendLabels = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
            $ordTrendRaw = (clone $ordQuery)->selectRaw('MONTH(date_enacted) as m, COUNT(*) as c')->groupBy('m')->pluck('c', 'm');
            for($i=1; $i<=12; $i++) $ordTrendData[] = $ordTrendRaw[$i] ?? 0;
        } elseif ($ordTrendTime === 'weekly') {
            for ($i = 1; $i <= 52; $i++) $ordTrendLabels[] = "Wk $i";
            $ordTrendRaw = (clone $ordQuery)->selectRaw('WEEK(date_enacted, 1) as w, COUNT(*) as c')->groupBy('w')->pluck('c', 'w');
            for($i=1; $i<=52; $i++) $ordTrendData[] = $ordTrendRaw[$i] ?? 0;
        } elseif ($ordTrendTime === 'annual') {
            for ($i = $currentYear - 4; $i <= $currentYear; $i++) $ordTrendLabels[] = (string)$i;
            
            $ordAnnualQuery = Ordinance::query();
            if ($ordStatus !== 'all') $ordAnnualQuery->where('status_id', $ordStatus);
            if ($ordActive !== 'all') $ordAnnualQuery->where('is_active', $ordActive === 'active' ? 1 : 0);
            if ($ordIrr === 'with') $ordAnnualQuery->has('implementingRules');
            if ($ordIrr === 'without') $ordAnnualQuery->doesntHave('implementingRules');

            $ordTrendRaw = $ordAnnualQuery->selectRaw('YEAR(date_enacted) as y, COUNT(*) as c')->whereBetween(DB::raw('YEAR(date_enacted)'), [$currentYear - 4, $currentYear])->groupBy('y')->pluck('c', 'y');
            foreach($ordTrendLabels as $yearLabel) $ordTrendData[] = $ordTrendRaw[$yearLabel] ?? 0;
        }

        // Ord Status Data
        $ordStatusRaw = (clone $ordQuery)->selectRaw('status_id, COUNT(*) as c')->groupBy('status_id')->pluck('c', 'status_id');
        $ordStatusLabels = []; $ordStatusData = [];
        foreach($statuses as $s) {
            if(($ordStatusRaw[$s->id] ?? 0) > 0) {
                $ordStatusLabels[] = $s->name;
                $ordStatusData[] = $ordStatusRaw[$s->id];
            }
        }

        // --- 5. RECENT ACTIVITY (Strictly Active Only) ---
        $recent_eos = ExecutiveOrder::with('status')->where('is_active', 1)->latest('updated_at')->take(4)->get()
            ->map(fn($i) => ['id' => $i->id, 'number' => $i->eo_number, 'title' => $i->title, 'status' => $i->status ? $i->status->name : 'N/A', 'date' => $i->updated_at->diffForHumans()]);
            
        $recent_ords = Ordinance::with('status')->where('is_active', 1)->latest('updated_at')->take(4)->get()
            ->map(fn($i) => ['id' => $i->id, 'number' => $i->ordinance_number, 'title' => $i->title, 'status' => $i->status ? $i->status->name : 'N/A', 'date' => $i->updated_at->diffForHumans()]);

        // --- 6. TOP DEPARTMENTS ---
        $departments = Department::withCount(['executiveOrders', 'ordinances'])->get()->map(function($dept) {
            $dept->total_involved = $dept->executive_orders_count + $dept->ordinances_count;
            return $dept;
        })->filter(fn($d) => $d->total_involved > 0)->sortByDesc('total_involved')->take(8)->values();

        // --- RENDER ---
        return Inertia::render('Dashboard', [
            'stats' => [
                'total_eos' => $total_eos,
                'total_ordinances' => $total_ordinances,
                'ords_with_irrs' => $ords_with_irrs,
            ],
            'eo_analytics' => [
                'trend' => $eoTrendData,
                'trend_labels' => $eoTrendLabels,
                'class_labels' => $eoClassLabels,
                'class_data' => $eoClassData,
                'status_labels' => $eoStatusLabels,
                'status_data' => $eoStatusData,
            ],
            'ord_analytics' => [
                'trend' => $ordTrendData,
                'trend_labels' => $ordTrendLabels,
                'status_labels' => $ordStatusLabels,
                'status_data' => $ordStatusData,
            ],
            'recent_eos' => $recent_eos,
            'recent_ords' => $recent_ords,
            'top_departments' => $departments,
            'filters' => $request->only(['eo_year', 'eo_class', 'eo_status', 'eo_active', 'ord_year', 'ord_status', 'ord_irr', 'ord_active', 'eo_trend_time', 'ord_trend_time']),
            'available_years' => $available_years,
            'available_classifications' => $classifications,
            'available_statuses' => $statuses
        ]);
    }
}