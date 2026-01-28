<?php

namespace App\Http\Controllers;

use App\Models\ExecutiveOrder;
use App\Models\ImplementingRuleandRegulation;
use App\Models\Department;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Carbon\Carbon;

class DashboardController extends Controller
{
    public function index()
    {
        // --- 1. CARD STATS (The Top Row) ---
        
        // Total EOs Encoded
        $total_eos = ExecutiveOrder::count();

        // EOs specifically marked as "Active" or "Implemented" (Assuming ID 1 is Active/Implemented, adjust based on your Status seeder)
        // For now, let's count EOs issued THIS YEAR as a "Velocity" metric
        $eos_this_year = ExecutiveOrder::whereYear('date_issued', Carbon::now()->year)->count();

        // Pending IRRs (Rules that are stuck in 'Drafting' or 'Pending Approval')
        $pending_irrs = ImplementingRuleandRegulation::whereIn('status', ['Drafting', 'Pending Approval'])->count();

        // Active Departments (Offices that are assigned as LEAD for at least one EO)
        $active_offices = ExecutiveOrder::join('eo_department', 'executive_orders.id', '=', 'eo_department.executive_order_id')
            ->where('eo_department.role', 'lead')
            ->distinct('eo_department.department_id')
            ->count('eo_department.department_id');


        // --- 2. CHART DATA (The Green Area Chart) ---
        $monthly_counts = ExecutiveOrder::select(
                DB::raw('COUNT(id) as count'), 
                DB::raw('MONTH(date_issued) as month')
            )
            ->whereYear('date_issued', Carbon::now()->year)
            ->groupBy('month')
            ->orderBy('month')
            ->get()
            ->pluck('count', 'month'); // [1 => 5, 2 => 3, etc.]

        $chart_data = [];
        for ($i = 1; $i <= 12; $i++) {
            $chart_data[] = $monthly_counts[$i] ?? 0;
        }


        // --- 3. RECENT ACTIVITY (The Right Sidebar List) ---
        $recent_activity = ExecutiveOrder::with('status')
            ->orderBy('updated_at', 'desc')
            ->take(5)
            ->get()
            ->map(function ($eo) {
                return [
                    'id' => $eo->id,
                    'eo_number' => $eo->eo_number,
                    'title' => $eo->title,
                    'status' => $eo->status->name,
                    'date' => Carbon::parse($eo->updated_at)->diffForHumans(),
                ];
            });

        return Inertia::render('Dashboard', [
            'stats' => [
                'total_eos' => $total_eos,
                'eos_this_year' => $eos_this_year,
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