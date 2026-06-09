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
            $q->whereIn('status', ['Active', 'Implemented'])
              ->with('leadOffice'); 
        };

        if ($type === 'ordinance') {
            $query = Ordinance::with([
                'status', 
                'departments',                  
                'implementingRules' => $irrFilter, 
                'parentOrdinance', 
                'amendments',
                'committees.members', // include committees.members so public view can show committee members for ordinances
                'committees.registry'
            ]);
            
            // Deep Search for Ordinances
            if ($search) {
                $query->where(function($q) use ($search) {
                    $q->where('ordinance_number', 'LIKE', "%{$search}%")
                      ->orWhere('title', 'LIKE', "%{$search}%")
                      ->orWhere('subject_matter', 'LIKE', "%{$search}%")
                      ->orWhereRaw('LOWER(author_details) LIKE ?', ['%'.strtolower($search).'%'])
                      // 🚀 FIXED: Added deep search for the new external column!
                      ->orWhereRaw('LOWER(external_institutions) LIKE ?', ['%'.strtolower($search).'%'])
                      ->orWhereHas('departments', function($deptQuery) use ($search) {
                          $deptQuery->where('name', 'LIKE', "%{$search}%");
                      });
                });
            }
            
            if ($year) {
                $query->whereYear('date_enacted', $year); 
            }

            if ($isActive && $isActive !== 'all') {
                $query->where('is_active', $isActive === 'active' ? 1 : 0);
            }
            
            $query->orderBy('id', 'desc');

        } else {
            $query = ExecutiveOrder::with([
                'status', 
                'departments', 
                'parentEO', 
                'amendments',
                'committees.members',
                'committees.registry',
            ]);

            // Deep Search for Executive Orders
            if ($search) {
                $query->where(function($q) use ($search) {
                    $q->where('eo_number', 'LIKE', "%{$search}%")
                      ->orWhere('title', 'LIKE', "%{$search}%")
                      ->orWhere('subject_matter', 'LIKE', "%{$search}%")
                      ->orWhereHas('departments', function($deptQuery) use ($search) {
                          $deptQuery->where('name', 'LIKE', "%{$search}%");
                      });
                });
            }

            if ($year) {
                $query->whereYear('date_issued', $year);
            }

            if ($isActive && $isActive !== 'all') {
                $query->where('is_active', $isActive === 'active' ? 1 : 0);
            }
            
            $query->orderBy('id', 'desc');
        }
        $query->where('is_active', true);
        
        // 2. Must be strictly New or Amendment
        $query->whereHas('status', function($q) {
            $q->whereIn('name', ['New', 'Amendment']);
        });

        $query->orderBy('id', 'desc');

        $stats = [
            'total_eos' => ExecutiveOrder::where('is_active', true)->whereHas('status', fn($q) => $q->whereIn('name', ['New', 'Amendment']))->count(),
            'total_ordinances' => Ordinance::where('is_active', true)->whereHas('status', fn($q) => $q->whereIn('name', ['New', 'Amendment']))->count(),
            'latest_date' => now()->format('F d, Y'),
        ];

        $years = $type === 'ordinance' 
            ? Ordinance::selectRaw('YEAR(date_enacted) as year')->distinct()->orderBy('year', 'desc')->pluck('year')
            : ExecutiveOrder::selectRaw('YEAR(date_issued) as year')->distinct()->orderBy('year', 'desc')->pluck('year');

        // Paginate and ensure nested relation `committees.members` is loaded for each record
        $records = $query->paginate(10)->withQueryString();

        // In some environments nested eager loads can be lost during pagination/serialization;
        // defensively ensure `committees.members` is present on each item and add debug info.
        $records->getCollection()->transform(function ($item) {
            $item->loadMissing(['committees.members', 'committees.registry']);

            // compute a simple members count and a small serializable representation
            $memberCount = 0;
            $simple = [];
            $externalMembers = [];
            $externalNgos = [];
            $externalOthers = [];
            if ($item->relationLoaded('committees') && $item->committees) {
                foreach ($item->committees as $committee) {
                    $cExtMembers = [];
                    $cExtNgos = [];
                    $cExtOthers = [];

                    if ($committee->relationLoaded('members') && $committee->members) {
                        foreach ($committee->members as $m) {
                            $memberCount++;
                            $simple[] = [
                                'committee' => $committee->name,
                                'registry' => $committee->registry->name ?? null,
                                'role' => $m->pivot->role ?? null,
                                'name' => $m->name ?? null,
                            ];

                            // Collect external categories by pivot role keywords so the frontend has a direct source
                            $role = strtolower($m->pivot->role ?? '');
                            if (strpos($role, 'external') !== false) {
                                $externalMembers[] = $m->name ?? null;
                                $cExtMembers[] = $m->name ?? null;
                            } elseif (strpos($role, 'ngo') !== false) {
                                $externalNgos[] = $m->name ?? null;
                                $cExtNgos[] = $m->name ?? null;
                            } elseif (strpos($role, 'other') !== false) {
                                $externalOthers[] = $m->name ?? null;
                                $cExtOthers[] = $m->name ?? null;
                            }
                        }
                    }

                    // attach committee-scoped external lists for the frontend
                    $committee->external_members_from_pivot = array_values(array_filter($cExtMembers));
                    $committee->external_ngos_from_pivot = array_values(array_filter($cExtNgos));
                    $committee->external_others_from_pivot = array_values(array_filter($cExtOthers));
                }
            }

            // Attach debug attributes that will be serialized into the Inertia props
            $item->committee_member_count = $memberCount;
            $item->committee_members_simple = $simple;
            // Attach pivot-derived external member arrays for reliable frontend rendering
            $item->external_members_from_pivot = array_values(array_filter($externalMembers));
            $item->external_ngos_from_pivot = array_values(array_filter($externalNgos));
            $item->external_others_from_pivot = array_values(array_filter($externalOthers));

            return $item;
        });

        return Inertia::render('public/Home', [
            'records' => $records,
            'departments' => Department::select('id', 'name')->get(), 
            'filters' => $request->only(['search', 'year', 'type', 'is_active']),
            'years' => $years,
            'activeType' => $type, 
            'stats' => $stats,
        ]);
    }

    
}