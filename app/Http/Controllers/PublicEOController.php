<?php

namespace App\Http\Controllers;

use App\Models\ExecutiveOrder;
use App\Models\Department;
use Illuminate\Http\Request;
use Inertia\Inertia;

class PublicEOController extends Controller
{
    public function index(Request $request)
    {
        // Added 'amendments' and 'parentEO.status' to ensure we have all data for the warnings
        $query = ExecutiveOrder::with([
            'departments', 
            'status', 
            'implementingRules' => function($q) {
                $q->whereIn('status', ['Approved', 'Implemented']); 
            },
            'parentEO', 
            'amendments'
        ])
        // Only show finalized statuses (Optional: customize as needed)
        ->whereHas('status', function($q) {
            $q->where('name', '!=', 'Draft');
        })
        ->orderBy('date_issued', 'desc');

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('eo_number', 'LIKE', "%{$search}%")
                  ->orWhere('title', 'LIKE', "%{$search}%");
            });
        }

        if ($request->filled('year')) {
            $query->whereYear('date_issued', $request->year);
        }

        return Inertia::render('public/Home', [
            'eos' => $query->paginate(12)->withQueryString(),
            'filters' => $request->only(['search', 'year']),
            'years' => ExecutiveOrder::selectRaw('YEAR(date_issued) as year')
                ->distinct()
                ->orderBy('year', 'desc')
                ->pluck('year'),
        ]);
    }
}