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
        $type     = $request->input('type', 'eo');
        $search   = $request->search;
        $year     = $request->year;
        $isActive = $request->is_active;

        $irrFilter = function ($q) {
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
                'committees.members',
                'committees.registry',
                // ── FIX: eager-load the co-lead department for ordinance committees ──
                'committees.coLeadOffice',
            ]);

            if ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('ordinance_number', 'LIKE', "%{$search}%")
                      ->orWhere('title', 'LIKE', "%{$search}%")
                      ->orWhere('subject_matter', 'LIKE', "%{$search}%")
                      ->orWhere('document_content', 'LIKE', "%{$search}%")
                      
                      // 🚀 FIXED: Search inside attached IRR document text ONLY if active!
                      ->orWhereHas('implementingRules', function ($irrQuery) use ($search) {
                          $irrQuery->where('document_content', 'LIKE', "%{$search}%")
                                   ->where('is_active', true); // <-- This strict check prevents searching disabled IRRs
                      })
                      
                      ->orWhereHas('departments', function ($deptQuery) use ($search) {
                          $deptQuery->where('name', 'LIKE', "%{$search}%");
                      })
                      ->orWhereHas('committees.members', function ($memberQuery) use ($search) {
                          $memberQuery->where('name', 'LIKE', "%{$search}%");
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
                // ── FIX: eager-load the co-lead department for EO committees ──
                'committees.coLeadOffice',
            ]);

            if ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('eo_number', 'LIKE', "%{$search}%")
                      ->orWhere('title', 'LIKE', "%{$search}%")
                      ->orWhere('subject_matter', 'LIKE', "%{$search}%")
                      // 🚀 ADD THIS NEW LINE: Search inside the PDF text!
                      ->orWhere('document_content', 'LIKE', "%{$search}%")
                      // Search inside the linked departments
                      ->orWhereHas('departments', function ($deptQuery) use ($search) {
                          $deptQuery->where('name', 'LIKE', "%{$search}%");
                      })
                      // 🚀 FIX: Search inside the linked committee members (TWG, Internal, External)
                      ->orWhereHas('committees.members', function ($memberQuery) use ($search) {
                          $memberQuery->where('name', 'LIKE', "%{$search}%");
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

        // Only show active records with New or Amendment status
        $query->where('is_active', true);
        $query->whereHas('status', function ($q) {
            $q->whereIn('name', ['New', 'Amendment', 'Amended']);
        });
        $query->orderBy('id', 'desc');

        $stats = [
            'total_eos'       => ExecutiveOrder::where('is_active', true)->whereHas('status', fn($q) => $q->whereIn('name', ['New', 'Amendment']))->count(),
            'total_ordinances'=> Ordinance::where('is_active', true)->whereHas('status', fn($q) => $q->whereIn('name', ['New', 'Amendment']))->count(),
            'latest_date'     => now()->format('F d, Y'),
        ];

        $years = $type === 'ordinance'
            ? Ordinance::selectRaw('YEAR(date_enacted) as year')->distinct()->orderBy('year', 'desc')->pluck('year')
            : ExecutiveOrder::selectRaw('YEAR(date_issued) as year')->distinct()->orderBy('year', 'desc')->pluck('year');

        $records = $query->paginate(10)->withQueryString();

        $records->getCollection()->transform(function ($item) {
            // Ensure nested relations are present after pagination
            $item->loadMissing([
                'committees.members',
                'committees.registry',
                'committees.coLeadOffice', // ── FIX: also load here defensively ──
            ]);

            $memberCount    = 0;
            $simple         = [];
            $externalMembers = [];
            $externalNgos   = [];
            $externalOthers = [];

            if ($item->relationLoaded('committees') && $item->committees) {
                foreach ($item->committees as $committee) {
                    $cExtMembers = [];
                    $cExtNgos    = [];
                    $cExtOthers  = [];

                    if ($committee->relationLoaded('members') && $committee->members) {
                        foreach ($committee->members as $m) {
                            $memberCount++;
                            $simple[] = [
                                'committee' => $committee->name,
                                'registry'  => $committee->registry->name ?? null,
                                'role'      => $m->pivot->role ?? null,
                                'name'      => $m->name ?? null,
                            ];

                            $role = strtolower($m->pivot->role ?? '');
                            if (strpos($role, 'external') !== false) {
                                $externalMembers[] = $m->name ?? null;
                                $cExtMembers[]     = $m->name ?? null;
                            } elseif (strpos($role, 'ngo') !== false) {
                                $externalNgos[] = $m->name ?? null;
                                $cExtNgos[]     = $m->name ?? null;
                            } elseif (strpos($role, 'other') !== false) {
                                $externalOthers[] = $m->name ?? null;
                                $cExtOthers[]     = $m->name ?? null;
                            }
                        }
                    }

                    $committee->external_members_from_pivot = array_values(array_filter($cExtMembers));
                    $committee->external_ngos_from_pivot    = array_values(array_filter($cExtNgos));
                    $committee->external_others_from_pivot  = array_values(array_filter($cExtOthers));

                    // ── FIX: attach co_lead_office_name directly on the committee
                    //         so the frontend doesn't need to do a separate lookup ──
                    $committee->co_lead_office_name = $committee->coLeadOffice
                        ? $committee->coLeadOffice->name
                        : null;
                }
            }

            $item->committee_member_count   = $memberCount;
            $item->committee_members_simple = $simple;
            $item->external_members_from_pivot = array_values(array_filter($externalMembers));
            $item->external_ngos_from_pivot    = array_values(array_filter($externalNgos));
            $item->external_others_from_pivot  = array_values(array_filter($externalOthers));

            return $item;
        });

        return Inertia::render('public/Home', [
            'records'    => $records,
            'departments'=> Department::select('id', 'name')->get(),
            'filters'    => $request->only(['search', 'year', 'type', 'is_active']),
            'years'      => $years,
            'activeType' => $type,
            'stats'      => $stats,
        ]);
    }
}