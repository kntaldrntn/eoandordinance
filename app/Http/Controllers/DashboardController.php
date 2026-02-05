<?php

namespace App\Http\Controllers;

use App\Models\ExecutiveOrder;
use App\Models\Ordinance; // Import this
use App\Models\ImplementingRuleandRegulation;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Carbon\Carbon;

class DashboardController extends Controller
{
    public function index()
    {
        // --- 1. CARD STATS ---
        
        // Total Issuances (EOs + Ordinances)
        $total_eos = ExecutiveOrder::count();
        $total_ordinances = Ordinance::count(); // New Stat
        
        // Issuances THIS YEAR (Combined)
        $eos_this_year = ExecutiveOrder::whereYear('date_issued', Carbon::now()->year)->count();
        $ords_this_year = Ordinance::whereYear('date_enacted', Carbon::now()->year)->count();
        $total_this_year = $eos_this_year + $ords_this_year;

        // Pending IRRs (This model already covers both if you updated it!)
        $pending_irrs = ImplementingRuleandRegulation::whereIn('status', ['Drafting', 'Pending Approval'])->count();

        // Active Offices (Combine unique departments from both tables)
        // We use union to get unique department IDs involved in either system
        $active_offices = DB::table('eo_department')
            ->where('role', 'lead')
            ->select('department_id')
            ->union(
                DB::table('ordinance_department')
                ->where('role', 'sponsor')
                ->select('department_id')
            )
            ->count();

        // --- 2. CHART DATA (Combined Monthly Volume) ---
        // Helper function to get monthly counts
        $getMonthly = function($model, $dateCol) {
            return $model::select(DB::raw('COUNT(id) as count'), DB::raw('MONTH('.$dateCol.') as month'))
                ->whereYear($dateCol, Carbon::now()->year)
                ->groupBy('month')->pluck('count', 'month');
        };

        $eo_monthly = $getMonthly(ExecutiveOrder::class, 'date_issued');
        $ord_monthly = $getMonthly(Ordinance::class, 'date_enacted');

        $chart_data = [];
        for ($i = 1; $i <= 12; $i++) {
            // Add EO count + Ordinance Count for that month
            $count = ($eo_monthly[$i] ?? 0) + ($ord_monthly[$i] ?? 0);
            $chart_data[] = $count;
        }

        // --- 3. RECENT ACTIVITY (Merge & Sort) ---
        // Fetch top 5 from both, merge, sort by date, take top 5
        $latest_eos = ExecutiveOrder::with('status')->latest('updated_at')->take(5)->get()->map(fn($item) => [
            'id' => $item->id,
            'number' => $item->eo_number, // Normalize to generic 'number' key
            'title' => $item->title,
            'status' => $item->status->name,
            'type' => 'EO', // Add type identifier
            'date_raw' => $item->updated_at,
            'date' => Carbon::parse($item->updated_at)->diffForHumans(),
        ]);

        $latest_ords = Ordinance::with('status')->latest('updated_at')->take(5)->get()->map(fn($item) => [
            'id' => $item->id,
            'number' => $item->ordinance_number, // Normalize to generic 'number' key
            'title' => $item->title,
            'status' => $item->status->name,
            'type' => 'ORD', // Add type identifier
            'date_raw' => $item->updated_at,
            'date' => Carbon::parse($item->updated_at)->diffForHumans(),
        ]);

        // Merge, sort desc by date, take 5
        $recent_activity = $latest_eos->concat($latest_ords)
            ->sortByDesc('date_raw')
            ->take(5)
            ->values();

        return Inertia::render('Dashboard', [
            'stats' => [
                'total_eos' => $total_eos,
                'total_ordinances' => $total_ordinances, // Pass this to view
                'issued_this_year' => $total_this_year,  // Renamed for clarity
                'pending_irrs' => $pending_irrs,
                'active_offices' => $active_offices,
            ],
            'chart' => [
                'data' => $chart_data,
                'year' => Carbon::now()->year,
            ],
            'recent_activity' => $recent_activity,
        ]);
    }
}