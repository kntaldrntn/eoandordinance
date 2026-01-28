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
        $query = ExecutiveOrder::with(['departments', 'status', 'implementingRules', 'parentEO', 'amendments'])
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
            // Get available years for the dropdown
            'years' => ExecutiveOrder::selectRaw('YEAR(date_issued) as year')
                ->distinct()
                ->orderBy('year', 'desc')
                ->pluck('year'),
        ]);
    }
}