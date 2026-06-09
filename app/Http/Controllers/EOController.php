<?php

namespace App\Http\Controllers;

use App\Models\Department;
use App\Models\ExecutiveOrder;
use App\Models\CityEmployee;
use App\Models\ExternalMember;
use App\Models\Committee;
use App\Models\CommitteeMember;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class EOController extends Controller
{
    public function index(Request $request)
    {
        // ... (Keep your existing index logic exactly as it is) ...
        $requiredStatuses = ['New', 'Amendment', 'Suspended'];
        foreach ($requiredStatuses as $statusName) {
            if (!DB::table('statuses')->where('name', $statusName)->exists()) {
                DB::table('statuses')->insert(['name' => $statusName]);
            }
        }

        $query = ExecutiveOrder::with([
            'status',
            'departments',
            'parentEO',
            'implementingRules.leadOffice',
            'amendments',
            'audits.user',
            'committees.members'
        ]);

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('eo_number', 'LIKE', "%{$search}%")
                  ->orWhere('title', 'LIKE', "%{$search}%");
            });
        }

        if ($request->filled('year') && $request->year !== 'all') {
            $query->whereYear('date_issued', $request->year);
        }

        if ($request->filled('is_active') && $request->is_active !== 'all') {
            $query->where('is_active', $request->is_active === 'active');
        }

        $eos = $query->orderBy('id', 'desc')->paginate(10)->withQueryString();

        $employees = CityEmployee::with('department')->where('state', 1)->get()->map(function($e) {
            return [
                'pmis_id' => $e->pmis_id,
                'name' => $e->full_name,
                'title' => ($e->position ?? 'Staff') . ($e->department ? ' (' . $e->department->name . ')' : ''),
                'type' => 'Internal'
            ];
        });

        $externals = ExternalMember::where('is_active', true)->get()->map(function($e) {
            return [
                'pmis_id' => null,
                'name' => $e->full_name,
                'title' => $e->position . ($e->organization ? ' - ' . $e->organization : ''),
                'type' => 'External'
            ];
        });

        $registered = CommitteeMember::all()->map(function($m) {
            $isInternal = !empty($m->pmis_id);
            return [
                'id' => $m->id,
                'pmis_id' => $m->pmis_id,
                'name' => $m->name,
                'type' => $isInternal ? 'Internal(Registered)' : 'External(Registered)'
            ];
        });

        $peopleRegistry = $registered
            ->concat($employees)
            ->concat($externals)
            ->unique('name')
            ->sortBy('name')
            ->values()
            ->toArray();

        $years = ExecutiveOrder::selectRaw('YEAR(date_issued) as year')
            ->whereNotNull('date_issued')
            ->distinct()
            ->orderBy('year', 'desc')
            ->pluck('year')
            ->toArray();

        return Inertia::render('eo/Index', [
            'eos' => $eos,
            'departments' => Department::orderBy('name')->get(),
            'peopleRegistry' => $peopleRegistry,
            'statuses' => DB::table('statuses')->orderBy('id')->get(),
            'classifications' => DB::table('classifications')->orderBy('name')->get(),
            'existing_eos' => ExecutiveOrder::select('id', 'eo_number', 'title', 'is_active', 'effectivity_date')->orderBy('eo_number', 'desc')->get(),
            'filters' => $request->only(['search', 'year', 'is_active']),
            'available_years' => $years,
            'flash' => [
                'success' => session('success'),
                'error' => session('error')
            ]
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'amends_eo_id' => 'nullable|exists:executive_orders,id',
            'eo_number' => 'required|string|unique:executive_orders,eo_number',
            'title' => 'required|string|max:1000',
            'classification_id' => 'nullable|exists:classifications,id',
            'date_issued' => 'required|date',
            'effectivity_date' => 'nullable|date|after_or_equal:date_issued',
            'legal_basis' => 'nullable|string',
            'lead_office_id' => 'nullable|exists:departments,id',
            'status_id' => 'required|exists:statuses,id',
            'file' => 'nullable|file|mimes:pdf|max:20480',
            'declaration' => 'nullable|string',
        ]);

        // 🚀 THE FIX: Extract and decode exactly like update() does
        $committeeData = $request->input('committee_details');
        if (is_string($committeeData)) {
            $committeeData = json_decode($committeeData, true);
        }

        DB::transaction(function () use ($request, $validated, $committeeData) {
            if (!empty($validated['amends_eo_id'])) {
                $oldEO = ExecutiveOrder::find($validated['amends_eo_id']);
                $amendedStatus = DB::table('statuses')->where('name', 'Amended')->first();
                $amendedStatusId = $amendedStatus ? $amendedStatus->id : DB::table('statuses')->insertGetId(['name' => 'Amended']);

                if ($oldEO) {
                    $oldEO->update(['status_id' => $amendedStatusId, 'is_active' => false]);
                }
            }

            $path = $request->hasFile('file') ? $request->file('file')->store('eos', 'public') : null;
            $statusName = DB::table('statuses')->where('id', $validated['status_id'])->value('name');
            $isActive = !in_array($statusName, ['Suspended', 'Amended', 'Repealed', 'Superseded']);

            $eo = ExecutiveOrder::create([
                'amends_eo_id' => $validated['amends_eo_id'] ?? null,
                'relationship_type' => !empty($validated['amends_eo_id']) ? 'Amends' : null,
                'eo_number' => $validated['eo_number'],
                'title' => $validated['title'],
                'classification_id' => $validated['classification_id'] ?? null,
                'date_issued' => $validated['date_issued'],
                'effectivity_date' => $validated['effectivity_date'],
                'legal_basis' => $validated['legal_basis'],
                'issuing_authority' => 'City Mayor',
                'status_id' => $validated['status_id'],
                'is_active' => $isActive,
                'declaration' => $validated['declaration'] ?? null,
                'file_path' => $path,
            ]);

            if (!empty($validated['lead_office_id'])) {
                $eo->departments()->attach($validated['lead_office_id'], ['role' => 'lead']);
            }

            // 🚀 Now pass the safely decoded array to the process function
            if (!empty($committeeData)) {
                $this->processCommittee($eo, $committeeData);
            }
        });

        return redirect()->back()->with('success', 'Executive Order encoded successfully.');
    }

    public function update(Request $request, ExecutiveOrder $eo)
    {
        $validated = $request->validate([
            'amends_eo_id' => 'nullable|exists:executive_orders,id',
            'eo_number' => 'required|string|unique:executive_orders,eo_number,' . $eo->id,
            'title' => 'required|string|max:1000',
            'classification_id' => 'nullable|exists:classifications,id',
            'date_issued' => 'required|date',
            'effectivity_date' => 'nullable|date|after_or_equal:date_issued',
            'legal_basis' => 'nullable|string',
            'lead_office_id' => 'nullable|exists:departments,id',
            'status_id' => 'required|exists:statuses,id',
            'file' => 'nullable|file|mimes:pdf|max:20480',
            'declaration' => 'nullable|string',
        ]);

        $committeeData = $request->input('committee_details');

        if (is_string($committeeData)) {
            $committeeData = json_decode($committeeData, true);
        }

        DB::transaction(function () use ($request, $validated, $eo, $committeeData) {

            if (!empty($validated['amends_eo_id']) && $eo->amends_eo_id != $validated['amends_eo_id']) {
                $oldEO = ExecutiveOrder::find($validated['amends_eo_id']);
                $amendedStatus = DB::table('statuses')->where('name', 'Amended')->first();
                $amendedStatusId = $amendedStatus ? $amendedStatus->id : DB::table('statuses')->insertGetId(['name' => 'Amended']);

                if ($oldEO) {
                    $oldEO->update(['status_id' => $amendedStatusId, 'is_active' => false]);
                }
            }

            if ($request->hasFile('file')) {
                if ($eo->file_path && Storage::disk('public')->exists($eo->file_path)) {
                    Storage::disk('public')->delete($eo->file_path);
                }
                $eo->file_path = $request->file('file')->store('eos', 'public');
            }

            $statusName = DB::table('statuses')->where('id', $validated['status_id'])->value('name');
            $isActive = !in_array($statusName, ['Suspended', 'Amended', 'Repealed', 'Superseded']);

            $eo->update([
                'amends_eo_id' => $validated['amends_eo_id'] ?? null,
                'relationship_type' => !empty($validated['amends_eo_id']) ? 'Amends' : null,
                'eo_number' => $validated['eo_number'],
                'title' => $validated['title'],
                'classification_id' => $validated['classification_id'] ?? null,
                'date_issued' => $validated['date_issued'],
                'effectivity_date' => $validated['effectivity_date'],
                'legal_basis' => $validated['legal_basis'],
                'status_id' => $validated['status_id'],
                'is_active' => $isActive,
                'declaration' => $validated['declaration'] ?? null,
            ]);

            $eo->departments()->detach();
            if (!empty($validated['lead_office_id'])) {
                $eo->departments()->attach($validated['lead_office_id'], ['role' => 'lead']);
            }

            if (!empty($committeeData)) {
                $this->processCommittee($eo, $committeeData);
            }
        });

        return redirect()->back()->with('success', 'Executive Order updated successfully.');
    }

    private function processCommittee(ExecutiveOrder $eo, $details)
    {
        // Safety format check - Ensure it is an array before testing ['type']
        $data = is_string($details) ? json_decode($details, true) : $details;

        if (empty($data) || !isset($data['type']) || $data['type'] === 'none') {
            $existingCommittee = $eo->committees()->first();
            if ($existingCommittee) {
                $existingCommittee->delete();
            }
            $eo->committees()->detach();
            return;
        }

        $committee = Committee::updateOrCreate(
            ['name' => $eo->title . ' Committee'],
            ['type' => $data['type']]
        );

        $eo->committees()->syncWithoutDetaching([$committee->id]);

        $committee->members()->detach();

        $membersToSync = [];

        $addMember = function ($person, $role) use (&$membersToSync) {
            $id = is_array($person) ? ($person['id'] ?? null) : null;
            $pmisId = is_array($person) ? ($person['pmis_id'] ?? null) : null;
            $name = is_array($person) ? ($person['name'] ?? '') : $person;

            if (empty(trim($name))) return;

            $member = null;

            if ($id) {
                $member = CommitteeMember::find($id);
            }

            if (!$member && $pmisId) {
                $employee = \App\Models\CityEmployee::where('pmis_id', $pmisId)->first();
                if ($employee) {
                    $member = CommitteeMember::updateOrCreate(
                        ['pmis_id' => $pmisId],
                        ['name' => $employee->full_name, 'position' => $employee->position, 'agency' => $employee->department->name ?? 'City Government']
                    );
                }
            }

            if (!$member) {
                $member = CommitteeMember::firstOrCreate(['name' => $name], ['name' => $name, 'position' => 'External Partner']);
            }

            if ($member) $membersToSync[$member->id] = ['role' => $role];
        };

        if ($data['type'] === 'council' && isset($data['council'])) {
            $c = $data['council'];
            $addMember($c['chairman'] ?? '', 'Chairman');
            $addMember($c['co_chairman'] ?? '', 'Co-Chairman');
            $addMember($c['vice_chairman'] ?? '', 'Vice Chairman');
            $addMember($c['lead_secretariat'] ?? '', 'Lead Secretariat');
            $addMember($c['twg_head'] ?? '', 'TWG Head');

            $rolesMap = [
                'internal_members' => 'Internal Member',
                'external_members' => 'External Member',
                'secretariat_members' => 'Secretariat Member',
                'twg_internal_members' => 'TWG Internal',
                'twg_external_members' => 'TWG External'
            ];

            foreach ($rolesMap as $key => $roleName) {
                if (!empty($c[$key])) {
                    foreach ($c[$key] as $m) $addMember($m, $roleName);
                }
            }
        } elseif ($data['type'] === 'program' && isset($data['program'])) {
            $p = $data['program'];
            if (!empty($p['internal_members'])) {
                foreach ($p['internal_members'] as $m) $addMember($m, 'Program Internal');
            }
            if (!empty($p['external_members'])) {
                foreach ($p['external_members'] as $m) $addMember($m, 'Program External');
            }
        }
        $committee->members()->sync($membersToSync);
    }
}