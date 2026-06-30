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
use Spatie\PdfToText\Pdf;

class EOController extends Controller
{
    public function index(Request $request)
    {
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

        // 🚀 CHANGED: expose position/agency directly instead of a combined "title" string,
        // so the frontend can auto-fill the new Position + Agency inputs.
        $employees = CityEmployee::with('department')->where('state', 1)->get()->map(function($e) {
            return [
                'pmis_id'  => $e->pmis_id,
                'name'     => $e->full_name,
                'position' => $e->position ?? 'Staff',
                'agency'   => $e->department->name ?? null,
                'type'     => 'Internal'
            ];
        });

        $externals = ExternalMember::where('is_active', true)->get()->map(function($e) {
            return [
                'pmis_id'  => null,
                'name'     => $e->full_name,
                'position' => $e->position,
                'agency'   => $e->organization,
                'type'     => 'External'
            ];
        });

        $registered = CommitteeMember::all()->map(function($m) {
            $isInternal = !empty($m->pmis_id);
            return [
                'id'       => $m->id,
                'pmis_id'  => $m->pmis_id,
                'name'     => $m->name,
                'position' => $m->position,
                'agency'   => $m->agency,
                'type'     => $isInternal ? 'Internal(Registered)' : 'External(Registered)'
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
            'amends_eo_id'     => 'nullable|exists:executive_orders,id',
            'eo_number'        => 'required|string|unique:executive_orders,eo_number',
            'title'            => 'required|string|max:1000',
            'classification_id'=> 'nullable|exists:classifications,id',
            'date_issued'      => 'required|date',
            'effectivity_date' => 'nullable|date|after_or_equal:date_issued',
            'legal_basis'      => 'nullable|string',
            'lead_office_id'   => 'nullable|exists:departments,id',
            'status_id'        => 'required|exists:statuses,id',
            'file'             => 'nullable|file|mimes:pdf|max:20480',
            'declaration'      => 'nullable|string',
        ]);

        $committeeData = $request->input('committee_details');
        if (is_string($committeeData)) {
            $committeeData = json_decode($committeeData, true);
        }

        DB::transaction(function () use ($request, $validated, $committeeData) {

            if (!empty($validated['amends_eo_id'])) {
                $oldEO = ExecutiveOrder::find($validated['amends_eo_id']);
                $amendedStatus = DB::table('statuses')->where('name', 'Amended')->first();
                $amendedStatusId = $amendedStatus
                    ? $amendedStatus->id
                    : DB::table('statuses')->insertGetId(['name' => 'Amended']);

                if ($oldEO) {
                    $oldEO->update(['status_id' => $amendedStatusId, 'is_active' => false]);
                }
            }

            $path = null;
            $extractedText = null;

            if ($request->hasFile('file')) {
                $path = $request->file('file')->store('eos', 'public');

                try {
                    $absolutePath = storage_path('app/public/' . $path);
                    $extractedText = (new Pdf('C:/poppler/Library/bin/pdftotext.exe'))
                        ->setPdf($absolutePath)
                        ->text();
                } catch (\Exception $e) {
                    $extractedText = null;
                }
            }

            $statusName = DB::table('statuses')->where('id', $validated['status_id'])->value('name');
            $isActive   = !in_array($statusName, ['Suspended', 'Amended', 'Repealed', 'Superseded']);

            $eo = ExecutiveOrder::create([
                'amends_eo_id'      => $validated['amends_eo_id'] ?? null,
                'relationship_type' => !empty($validated['amends_eo_id']) ? 'Amends' : null,
                'eo_number'         => $validated['eo_number'],
                'title'             => $validated['title'],
                'classification_id' => $validated['classification_id'] ?? null,
                'date_issued'       => $validated['date_issued'],
                'effectivity_date'  => $validated['effectivity_date'],
                'legal_basis'       => $validated['legal_basis'],
                'issuing_authority' => 'City Mayor',
                'status_id'         => $validated['status_id'],
                'is_active'         => $isActive,
                'declaration'       => $validated['declaration'] ?? null,
                'file_path'         => $path,
                'document_content'  => $extractedText,
            ]);

            if (!empty($validated['lead_office_id'])) {
                $eo->departments()->attach($validated['lead_office_id'], ['role' => 'lead']);
            }

            $this->processCommittee($eo, $committeeData);
        });

        return redirect()->back()->with('success', 'Executive Order encoded successfully.');
    }

    public function update(Request $request, ExecutiveOrder $eo)
    {
        $validated = $request->validate([
            'amends_eo_id'     => 'nullable|exists:executive_orders,id',
            'eo_number'        => 'required|string|unique:executive_orders,eo_number,' . $eo->id,
            'title'            => 'required|string|max:1000',
            'classification_id'=> 'nullable|exists:classifications,id',
            'date_issued'      => 'required|date',
            'effectivity_date' => 'nullable|date|after_or_equal:date_issued',
            'legal_basis'      => 'nullable|string',
            'lead_office_id'   => 'nullable|exists:departments,id',
            'status_id'        => 'required|exists:statuses,id',
            'file'             => 'nullable|file|mimes:pdf|max:20480',
            'declaration'      => 'nullable|string',
        ]);

        $committeeData = $request->input('committee_details');
        if (is_string($committeeData)) {
            $committeeData = json_decode($committeeData, true);
        }

        DB::transaction(function () use ($request, $validated, $eo, $committeeData) {

            if (!empty($validated['amends_eo_id']) && $eo->amends_eo_id != $validated['amends_eo_id']) {
                $oldEO = ExecutiveOrder::find($validated['amends_eo_id']);
                $amendedStatus = DB::table('statuses')->where('name', 'Amended')->first();
                $amendedStatusId = $amendedStatus
                    ? $amendedStatus->id
                    : DB::table('statuses')->insertGetId(['name' => 'Amended']);

                if ($oldEO) {
                    $oldEO->update(['status_id' => $amendedStatusId, 'is_active' => false]);
                }
            }

            $extractedText = $eo->document_content;

            if ($request->hasFile('file')) {
                if ($eo->file_path && Storage::disk('public')->exists($eo->file_path)) {
                    Storage::disk('public')->delete($eo->file_path);
                }

                $path = $request->file('file')->store('eos', 'public');
                $eo->file_path = $path;

                try {
                    $absolutePath = storage_path('app/public/' . $path);
                    $extractedText = (new Pdf('C:/poppler/Library/bin/pdftotext.exe'))
                        ->setPdf($absolutePath)
                        ->text();
                } catch (\Exception $e) {
                    $extractedText = null;
                }
            }

            $statusName = DB::table('statuses')->where('id', $validated['status_id'])->value('name');
            $isActive   = !in_array($statusName, ['Suspended', 'Amended', 'Repealed', 'Superseded']);

            $eo->update([
                'amends_eo_id'      => $validated['amends_eo_id'] ?? null,
                'relationship_type' => !empty($validated['amends_eo_id']) ? 'Amends' : null,
                'eo_number'         => $validated['eo_number'],
                'title'             => $validated['title'],
                'classification_id' => $validated['classification_id'] ?? null,
                'date_issued'       => $validated['date_issued'],
                'effectivity_date'  => $validated['effectivity_date'],
                'legal_basis'       => $validated['legal_basis'],
                'status_id'         => $validated['status_id'],
                'is_active'         => $isActive,
                'declaration'       => $validated['declaration'] ?? null,
                'document_content'  => $extractedText,
            ]);

            $eo->departments()->detach();
            if (!empty($validated['lead_office_id'])) {
                $eo->departments()->attach($validated['lead_office_id'], ['role' => 'lead']);
            }

            $this->processCommittee($eo, $committeeData);
        });

        return redirect()->back()->with('success', 'Executive Order updated successfully.');
    }

    /**
     * Handles creating/updating/deleting the committee or program structure for an EO.
     *
     * UPDATED: members now carry { id, pmis_id, name, position, agency } from the
     * frontend instead of just a name. Position/agency are persisted on the shared
     * CommitteeMember record (these fields are global to the person, not per-EO),
     * so editing them here updates the registry entry too.
     */
    private function processCommittee(ExecutiveOrder $eo, $details)
    {
        $data = is_string($details) ? json_decode($details, true) : $details;

        // ── CASE 1: No committee / none selected ────────────────────────────
        if (empty($data) || !isset($data['type']) || $data['type'] === 'none') {
            $oldCommittees = $eo->committees()->get();
            $eo->committees()->detach();
            foreach ($oldCommittees as $old) {
                if ($old->executiveOrders()->count() === 0) {
                    $old->members()->detach();
                    $old->delete();
                }
            }
            return;
        }

        // ── CASE 2: council or program ───────────────────────────────────────
        $oldCommittees = $eo->committees()->get();
        $eo->committees()->detach();
        foreach ($oldCommittees as $old) {
            if ($old->executiveOrders()->count() === 0) {
                $old->members()->detach();
                $old->delete();
            }
        }

        $committeePayload = [
            'name' => $eo->title . ' Committee',
            'type' => $data['type'],
        ];

        if ($data['type'] === 'program') {
            $coLeadId = $data['program']['co_lead_office_id'] ?? null;
            $committeePayload['co_lead_office_id'] = ($coLeadId !== null && $coLeadId !== '' && $coLeadId !== '0')
                ? (int) $coLeadId
                : null;
        }

        $committee = Committee::create($committeePayload);
        $eo->committees()->attach($committee->id);

        $membersToSync = [];

        /**
         * Helper: resolve a person to a CommitteeMember record, persist any
         * Position/Agency edits made on this form, and queue it for the pivot sync.
         *
         * $person can be:
         *   - an array with keys: id, pmis_id, name, position, agency
         *   - a plain string (fallback)
         */
        $addMember = function ($person, string $role) use (&$membersToSync) {
            $id       = is_array($person) ? ($person['id']       ?? null) : null;
            $pmisId   = is_array($person) ? ($person['pmis_id']   ?? null) : null;
            $name     = is_array($person) ? ($person['name']      ?? '')   : $person;
            $position = is_array($person) ? trim((string) ($person['position'] ?? '')) : '';
            $agency   = is_array($person) ? trim((string) ($person['agency']   ?? '')) : '';

            if (empty(trim((string) $name))) {
                return; // skip blank entries
            }

            $member = null;

            // Priority 1: look up by existing CommitteeMember PK
            if ($id) {
                $member = CommitteeMember::find($id);
            }

            // Priority 2: match/create from PMIS employee record
            if (!$member && $pmisId) {
                $employee = CityEmployee::where('pmis_id', $pmisId)->first();
                if ($employee) {
                    $member = CommitteeMember::updateOrCreate(
                        ['pmis_id' => $pmisId],
                        [
                            'name'     => $employee->full_name,
                            'position' => $position !== '' ? $position : $employee->position,
                            'agency'   => $agency !== '' ? $agency : ($employee->department->name ?? 'City Government'),
                        ]
                    );
                }
            }

            // Priority 3: match/create by name only (external / free-typed)
            if (!$member) {
                $member = CommitteeMember::firstOrCreate(
                    ['name' => $name],
                    [
                        'name'     => $name,
                        'position' => $position !== '' ? $position : 'External Partner',
                        'agency'   => $agency !== '' ? $agency : null,
                    ]
                );
            }

            // Sync any Position/Agency edits made on THIS form back to the shared record
            if ($member) {
                $updates = [];
                if ($position !== '') $updates['position'] = $position;
                if ($agency !== '')   $updates['agency']   = $agency;
                if (!empty($updates)) {
                    $member->fill($updates);
                    if ($member->isDirty()) {
                        $member->save();
                    }
                }
            }

            if ($member) {
                $membersToSync[$member->id] = ['role' => $role];
            }
        };

        // ── COUNCIL / COMMITTEE / TWG ────────────────────────────────────────
        if ($data['type'] === 'council' && isset($data['council'])) {
            $c = $data['council'];

            $addMember($c['chairman']         ?? '', 'Chairman');
            $addMember($c['co_chairman']      ?? '', 'Co-Chairman');
            $addMember($c['vice_chairman']    ?? '', 'Vice Chairman');
            $addMember($c['lead_secretariat'] ?? '', 'Lead Secretariat');
            $addMember($c['twg_head']         ?? '', 'TWG Head');

            $rolesMap = [
                'internal_members'     => 'Internal Member',
                'external_members'     => 'External Member',
                'secretariat_members'  => 'Secretariat Member',
                'twg_internal_members' => 'TWG Internal',
                'twg_external_members' => 'TWG External',
            ];

            foreach ($rolesMap as $key => $roleName) {
                if (!empty($c[$key]) && is_array($c[$key])) {
                    foreach ($c[$key] as $m) {
                        $addMember($m, $roleName);
                    }
                }
            }
        }

        // ── PROGRAM-BASED INITIATIVE ─────────────────────────────────────────
        elseif ($data['type'] === 'program' && isset($data['program'])) {
            $p = $data['program'];

            if (!empty($p['internal_members']) && is_array($p['internal_members'])) {
                foreach ($p['internal_members'] as $m) {
                    $addMember($m, 'Program Internal');
                }
            }

            if (!empty($p['external_members']) && is_array($p['external_members'])) {
                foreach ($p['external_members'] as $m) {
                    $addMember($m, 'Program External');
                }
            }
        }

        $committee->members()->sync($membersToSync);
    }
}