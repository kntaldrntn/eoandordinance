<?php

namespace App\Http\Controllers;

use App\Models\ExecutiveOrder;
use App\Models\Ordinance;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Carbon\Carbon;

class MembershipController extends Controller
{
    public function index(Request $request)
    {
        $userName = auth()->user()->name; 

        // 1. Get Filters
        $filterType = $request->input('type', 'all'); // NEW: Document Type Filter
        $filterYear = $request->input('year', 'all'); 
        $filterActive = $request->input('is_active', 'all'); 
        $filterClass = $request->input('classification', 'all');

        $eoMemberships = collect();
        $ordMemberships = collect();

        // ==========================================
        // QUERY 1: EXECUTIVE ORDERS
        // ==========================================
        if ($filterType === 'all' || $filterType === 'Executive Order') {
            $eoQuery = ExecutiveOrder::whereRaw('LOWER(committee_details) LIKE ?', ['%' . strtolower($userName) . '%'])->with('status');

            if ($filterYear !== 'all') $eoQuery->whereYear('date_issued', $filterYear);
            if ($filterActive !== 'all') $eoQuery->where('is_active', $filterActive === 'active');
            if ($filterClass !== 'all') $eoQuery->where('classification', $filterClass);

            $eoMemberships = $eoQuery->get()->map(function ($eo) use ($userName) {
                return [
                    'id' => 'eo_' . $eo->id,
                    'number' => $eo->eo_number,
                    'title' => $eo->title,
                    'type' => 'Executive Order',
                    'role' => $this->extractRoleFromEOJSON($eo->committee_details, $userName),
                    'status' => $eo->status ? $eo->status->name : 'Unknown',
                    'is_active' => $eo->is_active,
                    'date' => $eo->date_issued ? Carbon::parse($eo->date_issued)->format('M d, Y') : '—',
                    'sort_date' => $eo->date_issued,
                    'url' => $eo->file_url ?? null,
                ];
            });
        }

        // ==========================================
        // QUERY 2: ORDINANCES
        // ==========================================
        if ($filterType === 'all' || $filterType === 'Ordinance') {
            $ordQuery = Ordinance::whereRaw('LOWER(author_details) LIKE ?', ['%' . strtolower($userName) . '%'])->with('status');

            if ($filterYear !== 'all') $ordQuery->whereYear('date_enacted', $filterYear);
            if ($filterActive !== 'all') $ordQuery->where('is_active', $filterActive === 'active');
            // Note: Ordinances don't use classifications, so we skip $filterClass here

            $ordMemberships = $ordQuery->get()->map(function ($ord) use ($userName) {
                return [
                    'id' => 'ord_' . $ord->id,
                    'number' => $ord->ordinance_number,
                    'title' => $ord->title,
                    'type' => 'Ordinance',
                    'role' => $this->extractRoleFromOrdJSON($ord->author_details, $userName),
                    'status' => $ord->status ? $ord->status->name : 'Unknown',
                    'is_active' => $ord->is_active,
                    'date' => $ord->date_enacted ? Carbon::parse($ord->date_enacted)->format('M d, Y') : '—',
                    'sort_date' => $ord->date_enacted,
                    'url' => $ord->file_url ?? null,
                ];
            });
        }

        // ==========================================
        // MERGE, SORT, AND FILTER
        // ==========================================
        
        $allMemberships = $eoMemberships->concat($ordMemberships);

        // If a classification filter is applied, strict filter out Ordinances just to be safe
        if ($filterClass !== 'all') {
            $allMemberships = $allMemberships->filter(function($item) {
                return $item['type'] === 'Executive Order';
            });
        }

        $memberships = $allMemberships->sortByDesc('sort_date')->values();

        // ==========================================
        // DYNAMIC DROPDOWN OPTIONS
        // ==========================================
        $eoYears = ExecutiveOrder::selectRaw('YEAR(date_issued) as year')->whereNotNull('date_issued')->pluck('year');
        $ordYears = Ordinance::selectRaw('YEAR(date_enacted) as year')->whereNotNull('date_enacted')->pluck('year');
        
        $years = $eoYears->concat($ordYears)->unique()->sortDesc()->values()->toArray();
        $classifications = ExecutiveOrder::whereNotNull('classification')->distinct()->pluck('classification')->toArray();

        return Inertia::render('membership/Index', [
            'memberships' => $memberships,
            'userName' => $userName,
            'filters' => [
                'type' => $filterType, // Passed back to the frontend
                'year' => $filterYear,
                'is_active' => $filterActive,
                'classification' => $filterClass,
            ],
            'available_years' => $years,
            'available_classifications' => $classifications,
        ]);
    }

    private function extractRoleFromEOJSON($details, $name)
    {
        if (is_string($details)) $details = json_decode($details, true);
        if (!$details || !is_array($details)) return 'Involved Member';

        $rolesFound = [];
        $searchName = strtolower(trim($name));
        $categories = ['council', 'program'];

        foreach ($categories as $category) {
            if (isset($details[$category]) && is_array($details[$category])) {
                foreach ($details[$category] as $roleKey => $membersString) {
                    if (is_string($membersString) && str_contains(strtolower($membersString), $searchName)) {
                        $formattedRole = ucwords(str_replace('_', ' ', $roleKey));
                        $formattedRole = str_replace('s', '', $formattedRole); 
                        if (str_ends_with($formattedRole, 'r')) $formattedRole .= 's'; 
                        $rolesFound[] = $formattedRole;
                    }
                }
            }
        }

        return count($rolesFound) > 0 ? implode(', ', $rolesFound) : 'Involved Member';
    }

    private function extractRoleFromOrdJSON($details, $name)
    {
        if (is_string($details)) $details = json_decode($details, true);
        if (!$details || !is_array($details)) return 'Author / Sponsor';

        $rolesFound = [];
        $searchName = strtolower(trim($name));

        $mapping = [
            'primary_author' => 'Primary Sponsor',
            'committee_chairmanship' => 'Committee Chair',
            'co_authors' => 'Co-Author',
            'external_institutions' => 'External Partner'
        ];

        foreach ($mapping as $key => $roleLabel) {
            if (isset($details[$key])) {
                $val = $details[$key];
                
                if (is_array($val)) {
                    foreach ($val as $v) {
                        if (is_string($v) && str_contains(strtolower($v), $searchName)) {
                            $rolesFound[] = $roleLabel;
                            break; 
                        }
                    }
                } 
                elseif (is_string($val) && str_contains(strtolower($val), $searchName)) {
                    $rolesFound[] = $roleLabel;
                }
            }
        }

        return count($rolesFound) > 0 ? implode(', ', array_unique($rolesFound)) : 'Author / Sponsor';
    }
}