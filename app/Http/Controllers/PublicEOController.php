<?php

namespace App\Http\Controllers;

use App\Models\ExecutiveOrder;
use App\Models\Ordinance; 
use Illuminate\Http\Request;
use Inertia\Inertia;

class PublicEOController extends Controller
{
    public function index(Request $request)
    {
        // 1. Get the current tab (Default to 'eo')
        $type = $request->input('type', 'eo'); 
        $search = $request->search;
        $year = $request->year;

        // 2. Select the Data Source based on the Tab
        if ($type === 'ordinance') {
            // --- ORDINANCE LOGIC ---
            $query = Ordinance::with([
                'status', 
                'departments',                  
                'implementingRules.leadOffice', 
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
            
            $query->orderBy('date_enacted', 'desc');

        } else {
            // --- EXECUTIVE ORDER LOGIC ---
            $query = ExecutiveOrder::with([
                'status', 
                'departments', 
                'implementingRules.leadOffice', 
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
            
            $query->orderBy('date_issued', 'desc');
        }

        
        $query->whereHas('status', function($q) {
            $q->where('name', '!=', 'Draft');
        });

       
        $years = $type === 'ordinance' 
            ? Ordinance::selectRaw('YEAR(date_enacted) as year')->distinct()->orderBy('year', 'desc')->pluck('year')
            : ExecutiveOrder::selectRaw('YEAR(date_issued) as year')->distinct()->orderBy('year', 'desc')->pluck('year');

        
        return Inertia::render('public/Home', [
            'records' => $query->paginate(12)->withQueryString(), // 
            'filters' => $request->only(['search', 'year', 'type']),
            'years' => $years,
            'activeType' => $type, 
        ]);
    }
}