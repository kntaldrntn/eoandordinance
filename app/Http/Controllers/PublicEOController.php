<?php

namespace App\Http\Controllers;

use App\Models\ExecutiveOrder;
use App\Models\Ordinance; 
use App\Models\Department;
use Illuminate\Http\Request;
use Inertia\Inertia;

class PublicEOController extends Controller
{
    public function index(Request $request)
    {
        $type = $request->input('type', 'eo'); 
        $search = $request->search;
        $year = $request->year;
        $isActive = $request->is_active;

        $irrFilter = function($q) {
            $q->whereIn('status', ['Approved', 'Implemented'])
              ->with('leadOffice'); 
        };

        if ($type === 'ordinance') {
            $query = Ordinance::with([
                'status', 
                'departments',                  
                'implementingRules' => $irrFilter, 
                'parentOrdinance', 
                'amendments'
            ]);
            
            if ($search) {
                $query->where(function($q) use ($search) {
                    $q->where('ordinance_number', 'LIKE', "%{$search}%")
                      ->orWhere('title', 'LIKE', "%{$search}%");
                });
            }
            
            if ($year) {
                $query->whereYear('date_enacted', $year);
            }

            if ($isActive && $isActive !== 'all') {
                $query->where('is_active', $isActive === 'active' ? 1 : 0);
            }
            
            $query->orderBy('date_enacted', 'desc')->orderBy('id', 'desc');

        } else {
            $query = ExecutiveOrder::with([
                'status', 
                'departments', 
                'implementingRules' => $irrFilter, 
                'parentEO', 
                'amendments'
            ]);

            if ($search) {
                $query->where(function($q) use ($search) {
                    $q->where('eo_number', 'LIKE', "%{$search}%")
                      ->orWhere('title', 'LIKE', "%{$search}%");
                });
            }

            if ($year) {
                $query->whereYear('date_issued', $year);
            }

            if ($isActive && $isActive !== 'all') {
                $query->where('is_active', $isActive === 'active' ? 1 : 0);
            }
            
            $query->orderBy('date_issued', 'desc')->orderBy('id', 'desc');
        }

        $query->whereHas('status', function($q) {
            $q->where('name', '!=', 'Draft');
        });

        $stats = [
            'total_eos' => ExecutiveOrder::whereHas('status', fn($q) => $q->where('name', 'Active'))->count(),
            'total_ordinances' => Ordinance::whereHas('status', fn($q) => $q->where('name', 'Active'))->count(),
            'latest_date' => now()->format('F d, Y'),
        ];

        $years = $type === 'ordinance' 
            ? Ordinance::selectRaw('YEAR(date_enacted) as year')->distinct()->orderBy('year', 'desc')->pluck('year')
            : ExecutiveOrder::selectRaw('YEAR(date_issued) as year')->distinct()->orderBy('year', 'desc')->pluck('year');

        return Inertia::render('public/Home', [
            'records' => $query->paginate(12)->withQueryString(),
            'departments' => Department::select('id', 'name')->get(), // <--- ADDED to resolve IDs to Names
            'filters' => $request->only(['search', 'year', 'type', 'is_active']),
            'years' => $years,
            'activeType' => $type, 
            'stats' => $stats,
        ]);
    }
}