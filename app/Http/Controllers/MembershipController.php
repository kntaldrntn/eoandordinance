<?php

namespace App\Http\Controllers;

use App\Models\CommitteeMember;
use App\Models\CityEmployee;
use App\Models\ExecutiveOrder;
use App\Models\Ordinance;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class MembershipController extends Controller
{
    public function index(Request $request)
    {
        $user = auth()->user();

        $committeeMember = null;

        if (!empty($user->committee_member_id)) {
            $committeeMember = CommitteeMember::find($user->committee_member_id);
        } elseif (!empty($user->pmis_id)) {
            $committeeMember = CommitteeMember::where('pmis_id', $user->pmis_id)->first();
        }
        // } else {
        //     $committeeMember = CommitteeMember::whereRaw('LOWER(name) = ?', [strtolower(trim($user->name))])->first();
        // }

        $filterType = $request->input('type', 'all');
        $filterYear = $request->input('year', 'all');
        $filterActive = $request->input('is_active', 'all');
        $filterClass = $request->input('classification', 'all');

        $eoMemberships = collect();
        $ordMemberships = collect();

        if ($committeeMember) {
            $committeeMember->load([
                'committees.executiveOrders.status',
                'committees.executiveOrders.classification',
                'committees.ordinances.status',
            ]);

            foreach ($committeeMember->committees as $committee) {
                $role = $committee->pivot->role ?? 'Member';

                if ($filterType === 'all' || $filterType === 'Executive Order') {
                    foreach ($committee->executiveOrders as $eo) {
                        if ($filterYear !== 'all' && (!$eo->date_issued || \Carbon\Carbon::parse($eo->date_issued)->year != $filterYear)) continue;
                        if ($filterActive !== 'all' && (bool)$eo->is_active !== ($filterActive === 'active')) continue;
                        if ($filterClass !== 'all' && $eo->classification_id != $filterClass) continue;

                        $eoMemberships->push([
                            'id' => 'eo_' . $eo->id . '_' . $committee->id,
                            'number' => $eo->eo_number,
                            'title' => $eo->title,
                            'type' => 'Executive Order',
                            'role' => $role,
                            'status' => $eo->status ? $eo->status->name : 'Unknown',
                            'is_active' => $eo->is_active,
                            'date' => $eo->date_issued ? \Carbon\Carbon::parse($eo->date_issued)->format('M d, Y') : '—',
                            'sort_date' => $eo->date_issued,
                            'url' => $eo->file_url ?? null,
                        ]);
                    }
                }

                if ($filterType === 'all' || $filterType === 'Ordinance') {
                    foreach ($committee->ordinances as $ord) {
                        if ($filterYear !== 'all' && (!$ord->date_enacted || \Carbon\Carbon::parse($ord->date_enacted)->year != $filterYear)) continue;
                        if ($filterActive !== 'all' && (bool)$ord->is_active !== ($filterActive === 'active')) continue;

                        $ordMemberships->push([
                            'id' => 'ord_' . $ord->id . '_' . $committee->id,
                            'number' => $ord->ordinance_number,
                            'title' => $ord->title,
                            'type' => 'Ordinance',
                            'role' => $role,
                            'status' => $ord->status ? $ord->status->name : 'Unknown',
                            'is_active' => $ord->is_active,
                            'date' => $ord->date_enacted ? \Carbon\Carbon::parse($ord->date_enacted)->format('M d, Y') : '—',
                            'sort_date' => $ord->date_enacted,
                            'url' => $ord->file_url ?? null,
                        ]);
                    }
                }
            }
        }

        $allMemberships = $eoMemberships->concat($ordMemberships);

        if ($filterClass !== 'all') {
            $allMemberships = $allMemberships->filter(fn($item) => $item['type'] === 'Executive Order');
        }

        $merged = $allMemberships->groupBy(fn($item) => $item['type'] . '_' . $item['number'])
            ->map(function ($group) {
                $first = $group->first();
                $first['role'] = $group->pluck('role')->unique()->implode(', ');
                return $first;
            })->values();

        $memberships = $merged->sortByDesc('sort_date')->values();

        $eoYears = ExecutiveOrder::selectRaw('YEAR(date_issued) as year')->whereNotNull('date_issued')->pluck('year');
        $ordYears = Ordinance::selectRaw('YEAR(date_enacted) as year')->whereNotNull('date_enacted')->pluck('year');
        $years = $eoYears->concat($ordYears)->unique()->sortDesc()->values()->toArray();

        $classifications = DB::table('classifications')->orderBy('name')->get();

        return Inertia::render('membership/Index', [
            'memberships' => $memberships,
            'userName' => $user->name,
            'filters' => [
                'type' => $filterType,
                'year' => $filterYear,
                'is_active' => $filterActive,
                'classification' => $filterClass,
            ],
            'available_years' => $years,
            'available_classifications' => $classifications,
        ]);
    }
}