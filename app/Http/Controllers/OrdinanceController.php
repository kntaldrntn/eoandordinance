<?php

namespace App\Http\Controllers;

use App\Models\Department;
use App\Models\Ordinance;
use App\Models\CityEmployee;
use App\Models\ExternalMember;
use App\Models\Committee;
use App\Models\CommitteeMember;
use App\Models\CommitteeRegistry;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class OrdinanceController extends Controller
{
    public function index(Request $request)
    {
        $requiredStatuses = ['New', 'Amendment', 'Suspended'];
        foreach ($requiredStatuses as $statusName) {
            if (!DB::table('statuses')->where('name', $statusName)->exists()) {
                DB::table('statuses')->insert(['name' => $statusName]);
            }
        }

        $query = Ordinance::with([
            'status',
            'departments',
            'parentOrdinance',
            'amendments',
            'implementingRules',
            'audits.user',
            'committees.members'
        ]);

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('ordinance_number', 'LIKE', "%{$search}%")
                  ->orWhere('title', 'LIKE', "%{$search}%");
            });
        }

        if ($request->filled('year') && $request->year !== 'all') {
            $query->whereYear('date_enacted', $request->year);
        }

        if ($request->filled('is_active') && $request->is_active !== 'all') {
            $query->where('is_active', $request->is_active === 'active');
        }

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

        // 🚀 FIXED: Added the specific Registered tagging logic from EO
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

        $years = Ordinance::selectRaw('YEAR(date_enacted) as year')
            ->whereNotNull('date_enacted')
            ->distinct()
            ->orderBy('year', 'desc')
            ->pluck('year')
            ->toArray();

        return Inertia::render('ordinances/Index', [
            'ordinances' => $query->orderBy('date_enacted', 'desc')->paginate(10)->withQueryString(),
            'departments' => Department::orderBy('name')->get(),
            'statuses' => DB::table('statuses')->orderBy('name')->get(),
            'existing_ordinances' => Ordinance::select('id', 'ordinance_number', 'title', 'is_active', 'effectivity_date')->orderBy('ordinance_number', 'desc')->get(),
            'peopleRegistry' => $peopleRegistry,
            'committeeRegistries' => CommitteeRegistry::with('members')->get(), // 🚀 PASSING THE NEW REGISTRY DATA
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
            'ordinance_number' => 'required|string|unique:ordinances,ordinance_number',
            'title' => 'required|string|max:1000',
            'subject_matter' => 'nullable|string',
            'date_approved' => 'required|date',
            'effectivity_date' => 'required|date|after_or_equal:date_approved',
            'date_enacted' => 'required|date|after_or_equal:effectivity_date',
            'presiding_officer' => 'required|string',
            'attested_by' => 'nullable|string',
            'approved_by' => 'nullable|string',
            'status_id' => 'required|exists:statuses,id',
            'file' => 'nullable|file|mimes:pdf|max:20480',
            'amends_ordinance_id' => 'nullable|exists:ordinances,id',
            'relationship_type' => 'nullable|string',
            'remarks' => 'nullable|string',
            'lead_office_id' => 'nullable|exists:departments,id',
            'support_office_ids' => 'nullable|array',
        ]);

        // 🚀 SAFELY EXTRACT AND DECODE FORM DATA STRINGS
        $authorDetails = $request->input('author_details');
        if (is_string($authorDetails)) $authorDetails = json_decode($authorDetails, true);

        $externalInstitutions = $request->input('external_institutions');
        if (is_string($externalInstitutions)) $externalInstitutions = json_decode($externalInstitutions, true);

        $ordinance = null;
        DB::transaction(function () use (&$ordinance, $request, $validated, $authorDetails, $externalInstitutions) {

            if (!empty($validated['amends_ordinance_id']) && !empty($validated['relationship_type'])) {
                $parent = Ordinance::find($validated['amends_ordinance_id']);
                $action = $validated['relationship_type'];

                if ($parent) {
                    $amendedStatusId = DB::table('statuses')->where('name', 'Amended')->value('id') ?? 1;

                    if ($action === 'Partial Amendment') {
                        $parent->update(['status_id' => $amendedStatusId, 'is_active' => true]);
                    } elseif ($action === 'Full Amendment') {
                        $parent->update(['status_id' => $amendedStatusId, 'is_active' => false]);
                    } elseif ($action === 'Repeals') {
                        $statusId = DB::table('statuses')->where('name', 'Repealed')->value('id') ?? 1;
                        $parent->update(['status_id' => $statusId, 'is_active' => false]);
                    } elseif ($action === 'Supersedes') {
                        $statusId = DB::table('statuses')->where('name', 'Supersede')->value('id') ?? 1;
                        $parent->update(['status_id' => $statusId, 'is_active' => false]);
                    }
                }
            }

            $path = $request->hasFile('file') ? $request->file('file')->store('ordinances', 'public') : null;
            $statusName = DB::table('statuses')->where('id', $validated['status_id'])->value('name');
            $isActive = !in_array($statusName, ['Suspended', 'Amended', 'Repealed', 'Repeal', 'Superseded', 'Supersede']);

            $ordinance = Ordinance::create([
                'ordinance_number' => $validated['ordinance_number'],
                'title' => $validated['title'],
                'subject_matter' => $validated['subject_matter'] ?? null,
                'date_enacted' => $validated['date_enacted'],
                'date_approved' => $validated['date_approved'],
                'effectivity_date' => $validated['effectivity_date'],
                'presiding_officer' => $validated['presiding_officer'],
                'attested_by' => $validated['attested_by'] ?? null,
                'approved_by' => $validated['approved_by'] ?? null,
                'status_id' => $validated['status_id'],
                'is_active' => $isActive,
                'file_path' => $path,
                'amends_ordinance_id' => $validated['amends_ordinance_id'] ?? null,
                'relationship_type' => $validated['relationship_type'] ?? null,
                'remarks' => $validated['remarks'] ?? null,
            ]);

            $syncData = [];
            if (!empty($validated['lead_office_id'])) {
                $syncData[$validated['lead_office_id']] = ['role' => 'lead'];
            }
            if (!empty($validated['support_office_ids'])) {
                foreach ($validated['support_office_ids'] as $id) {
                    if ($id) $syncData[$id] = ['role' => 'support'];
                }
            }
            $ordinance->departments()->sync($syncData);

            $this->processAuthorship($ordinance, $authorDetails, $externalInstitutions);
        });

        return redirect()->back()->with('success', 'Ordinance encoded successfully.');
    }

    public function update(Request $request, Ordinance $ordinance)
    {
        $validated = $request->validate([
            'ordinance_number' => 'required|string|unique:ordinances,ordinance_number,' . $ordinance->id,
            'title' => 'required|string|max:1000',
            'subject_matter' => 'nullable|string',
            'date_approved' => 'required|date',
            'effectivity_date' => 'required|date|after_or_equal:date_approved',
            'date_enacted' => 'required|date|after_or_equal:effectivity_date',
            'presiding_officer' => 'required|string',
            'attested_by' => 'nullable|string',
            'approved_by' => 'nullable|string',
            'status_id' => 'required|exists:statuses,id',
            'file' => 'nullable|file|mimes:pdf|max:20480',
            'amends_ordinance_id' => 'nullable|exists:ordinances,id',
            'relationship_type' => 'nullable|string',
            'remarks' => 'nullable|string',
            'lead_office_id' => 'nullable|exists:departments,id',
            'support_office_ids' => 'nullable|array',
        ]);

        // 🚀 SAFELY EXTRACT AND DECODE FORM DATA STRINGS
        $authorDetails = $request->input('author_details');
        if (is_string($authorDetails)) $authorDetails = json_decode($authorDetails, true);

        $externalInstitutions = $request->input('external_institutions');
        if (is_string($externalInstitutions)) $externalInstitutions = json_decode($externalInstitutions, true);

        DB::transaction(function () use ($request, $validated, $ordinance, $authorDetails, $externalInstitutions) {

            if (!empty($validated['amends_ordinance_id']) && !empty($validated['relationship_type'])) {
                if ($ordinance->amends_ordinance_id != $validated['amends_ordinance_id'] ||
                    $ordinance->relationship_type != $validated['relationship_type']) {

                    $parent = Ordinance::find($validated['amends_ordinance_id']);
                    $action = $validated['relationship_type'];

                    if ($parent) {
                        $amendedStatusId = DB::table('statuses')->where('name', 'Amended')->value('id') ?? 1;

                        if ($action === 'Partial Amendment') {
                            $parent->update(['status_id' => $amendedStatusId, 'is_active' => true]);
                        } elseif ($action === 'Full Amendment') {
                            $parent->update(['status_id' => $amendedStatusId, 'is_active' => false]);
                        } elseif ($action === 'Repeals') {
                            $statusId = DB::table('statuses')->where('name', 'Repeal')->value('id') ?? 1;
                            $parent->update(['status_id' => $statusId, 'is_active' => false]);
                        } elseif ($action === 'Supersedes') {
                            $statusId = DB::table('statuses')->where('name', 'Supersede')->value('id') ?? 1;
                            $parent->update(['status_id' => $statusId, 'is_active' => false]);
                        }
                    }
                }
            }

            if ($request->hasFile('file')) {
                if ($ordinance->file_path && Storage::disk('public')->exists($ordinance->file_path)) {
                    Storage::disk('public')->delete($ordinance->file_path);
                }
                $ordinance->file_path = $request->file('file')->store('ordinances', 'public');
            }

            $statusName = DB::table('statuses')->where('id', $validated['status_id'])->value('name');
            $isActive = !in_array($statusName, ['Suspended','Amended', 'Repealed', 'Repeal', 'Superseded', 'Supersede']);

            $ordinance->update([
                'ordinance_number' => $validated['ordinance_number'],
                'title' => $validated['title'],
                'subject_matter' => $validated['subject_matter'] ?? null,
                'date_enacted' => $validated['date_enacted'],
                'date_approved' => $validated['date_approved'],
                'effectivity_date' => $validated['effectivity_date'],
                'presiding_officer' => $validated['presiding_officer'],
                'attested_by' => $validated['attested_by'] ?? null,
                'approved_by' => $validated['approved_by'] ?? null,
                'status_id' => $validated['status_id'],
                'is_active' => $isActive,
                'amends_ordinance_id' => $validated['amends_ordinance_id'] ?? null,
                'relationship_type' => $validated['relationship_type'] ?? null,
                'remarks' => $validated['remarks'] ?? null,
            ]);

            $syncData = [];
            if (!empty($validated['lead_office_id'])) {
                $syncData[$validated['lead_office_id']] = ['role' => 'lead'];
            }
            if (!empty($validated['support_office_ids'])) {
                foreach ($validated['support_office_ids'] as $id) {
                    if ($id) $syncData[$id] = ['role' => 'support'];
                }
            }
            $ordinance->departments()->sync($syncData);

            $this->processAuthorship($ordinance, $authorDetails, $externalInstitutions);
        });

        return redirect()->back()->with('success', 'Ordinance updated successfully.');
    }

    private function processAuthorship(Ordinance $ordinance, $authorDetails, $externalInstitutions)
    {
        // processAuthorship
        $committee = Committee::firstOrCreate(
            ['name' => $ordinance->ordinance_number . ' Authorship'],
            ['type' => 'ordinance_sponsors']
        );

        $ordinance->committees()->syncWithoutDetaching([$committee->id]);

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
                $employee = \App\Models\CityEmployee::with('department')
                    ->where('pmis_id', $pmisId)
                    ->first();

                if ($employee) {
                    $member = \App\Models\CommitteeMember::updateOrCreate(
                        ['pmis_id' => $employee->pmis_id],
                        [
                            'name' => $employee->full_name,
                            'position' => $employee->position,
                            'agency' => $employee->department ? $employee->department->name : 'City Government'
                        ]
                    );
                }
            }

            if (!$member) {
                $member = \App\Models\CommitteeMember::firstOrCreate(
                    ['name' => $name],
                    [
                        'name' => $name,
                        'position' => 'External Partner',
                        'agency' => null
                    ]
                );
            }

            $membersToSync[$member->id] = ['role' => $role];
        };

        if (!empty($authorDetails)) {
            $a = $authorDetails;

            if (!empty($a['introduced_by'])) {
                $role = !empty($a['is_primary_author']) ? 'Primary Author' : 'Introduced By';
                $addMember($a['introduced_by'], $role);
            }

            if (!empty($a['committee_chairmanship'])) {
                $addMember($a['committee_chairmanship'], 'Committee Chairman');
            }

            if (!empty($a['co_authors'])) {
                foreach ($a['co_authors'] as $ca) $addMember($ca, 'Co-Author');
            }

            if (!empty($a['committee_members'])) {
                foreach ($a['committee_members'] as $cm) $addMember($cm, 'Committee Member');
            }
        }

        if (!empty($externalInstitutions)) {
            $e = $externalInstitutions;

            if (!empty($e['members'])) {
                foreach ($e['members'] as $em) $addMember($em, 'External Member');
            }
            if (!empty($e['ngos'])) {
                foreach ($e['ngos'] as $ngo) $addMember($ngo, 'NGO Partner');
            }
            if (!empty($e['others'])) {
                foreach ($e['others'] as $other) $addMember($other, 'Other Organization');
            }
        }

        // Also register Presiding/Attested/Approved persons as CommitteeMember entries (prefer internal registered)
        $specials = [
            'Presiding Officer' => $ordinance->presiding_officer,
            'Attested By' => $ordinance->attested_by,
            'Approved By' => $ordinance->approved_by,
        ];

        foreach ($specials as $roleName => $personName) {
            if (empty($personName)) continue;

            $member = null;

            // Try to find matching CityEmployee by full name
            $employee = \App\Models\CityEmployee::where('full_name', $personName)->with('department')->first();
            if ($employee) {
                $member = CommitteeMember::updateOrCreate(
                    ['pmis_id' => $employee->pmis_id],
                    ['name' => $employee->full_name, 'position' => $employee->position, 'agency' => $employee->department->name ?? 'City Government']
                );
            }

            if (!$member) {
                // Try to find existing registered committee member by name
                $member = CommitteeMember::whereRaw('LOWER(name) = ?', [strtolower(trim($personName))])->first();
            }

            if (!$member) {
                $member = CommitteeMember::firstOrCreate(
                    ['name' => $personName],
                    ['position' => 'External Partner']
                );
            }

            if ($member) {
                $membersToSync[$member->id] = ['role' => $roleName];
            }
        }

        // Determine registry id from author details or top-level input
        $regId = null;
        if (!empty($authorDetails) && !empty($authorDetails['selected_registry_id'])) {
            $regId = $authorDetails['selected_registry_id'];
        } elseif (request()->filled('selected_registry_id')) {
            $regId = request()->input('selected_registry_id');
        }
        // determined regId

        // If the user explicitly cleared the sponsorship committee (no name and no selected registry id),
        // ensure we also clear any persisted link on the committee record.
        if (!empty($authorDetails) && isset($authorDetails['sponsorship_committee'])) {
            $scName = trim($authorDetails['sponsorship_committee']['name'] ?? '');
            $scSelected = !empty($authorDetails['selected_registry_id']) || request()->filled('selected_registry_id');
            if ($scName === '' && !$scSelected) {
                $committee->registry_id = null;
                $committee->save();

                // Also remove any existing "Committee Member" role entries so the UI reflects a cleared sponsorship
                $existingCommitteeMemberIds = $committee->members()->wherePivot('role', 'Committee Member')->get()->pluck('id')->toArray();
                if (!empty($existingCommitteeMemberIds)) {
                    $committee->members()->detach($existingCommitteeMemberIds);
                }
            }
        }

        // If a registry id was provided in author details or top-level, import those members
        if (!empty($regId)) {
            $registry = CommitteeRegistry::with('members')->find($regId);
            if ($registry && $registry->members) {
                // Build maps for quick duplicate checks from already-prepared sync array
                $existingPmis = [];
                $existingNames = [];
                foreach ($membersToSync as $mid => $meta) {
                    $cm = CommitteeMember::find($mid);
                    if ($cm) {
                        if (!empty($cm->pmis_id)) $existingPmis[$cm->pmis_id] = $mid;
                        $existingNames[strtolower(trim($cm->name))] = $mid;
                    }
                }

                foreach ($registry->members as $m) {
                    // Determine authoritative CommitteeMember id for this registry member
                    $targetMemberId = null;

                    if (!empty($m->pmis_id)) {
                        // Ensure a CommitteeMember exists for this PMIS id (create/update from CityEmployee if possible)
                        $employee = \App\Models\CityEmployee::with('department')->where('pmis_id', $m->pmis_id)->first();
                        if ($employee) {
                            $cm = CommitteeMember::updateOrCreate(
                                ['pmis_id' => $employee->pmis_id],
                                ['name' => $employee->full_name, 'position' => $employee->position, 'agency' => $employee->department->name ?? 'City Government']
                            );
                            $targetMemberId = $cm->id;
                        }
                    }

                    if (!$targetMemberId) {
                        // Try to use existing CommitteeMember record from registry or match by name
                        $existingByNameId = $existingNames[strtolower(trim($m->name))] ?? null;
                        if ($existingByNameId) {
                            $targetMemberId = $existingByNameId;
                        } elseif (!empty($m->id)) {
                            // registry member likely already points to a CommitteeMember
                            $targetMemberId = $m->id;
                        } else {
                            // fallback: create or find by name
                            $cm = CommitteeMember::firstOrCreate(
                                ['name' => $m->name],
                                ['position' => 'External Partner']
                            );
                            $targetMemberId = $cm->id;
                        }
                    }

                    // Remove duplicates from membersToSync if they conflict with this registry member
                    // (by pmis or name)
                    if (!empty($m->pmis_id) && isset($existingPmis[$m->pmis_id])) {
                        unset($membersToSync[$existingPmis[$m->pmis_id]]);
                    }
                    if (isset($existingNames[strtolower(trim($m->name))])) {
                        $eid = $existingNames[strtolower(trim($m->name))];
                        if (isset($membersToSync[$eid])) unset($membersToSync[$eid]);
                    }

                    if ($targetMemberId) $membersToSync[$targetMemberId] = ['role' => 'Committee Member'];
                }

                // remember registry on committee
                $committee->registry_id = $registry->id;
                $committee->save();

                // If the user edited committee members in the form, persist those edits back to the registry
                // Build list of intended registry member ids from the posted author details (committee_members)
                $desiredRegistryMemberIds = [];
                if (!empty($authorDetails) && !empty($authorDetails['committee_members'])) {
                    foreach ($authorDetails['committee_members'] as $cm) {
                        if (empty($cm['name'])) continue;

                        $member = null;
                        if (!empty($cm['pmis_id'])) {
                            $employee = \App\Models\CityEmployee::with('department')->where('pmis_id', $cm['pmis_id'])->first();
                            if ($employee) {
                                $member = CommitteeMember::updateOrCreate(
                                    ['pmis_id' => $employee->pmis_id],
                                    ['name' => $employee->full_name, 'position' => $employee->position, 'agency' => $employee->department->name ?? 'City Government']
                                );
                            }
                        }

                        if (!$member) {
                            // If an id was provided and exists, prefer it
                            if (!empty($cm['id'])) {
                                $member = CommitteeMember::find($cm['id']);
                            }
                        }

                        if (!$member) {
                            $member = CommitteeMember::firstOrCreate(
                                ['name' => $cm['name']],
                                ['position' => $cm['position'] ?? 'External Partner']
                            );
                        }

                        if ($member) $desiredRegistryMemberIds[] = $member->id;
                    }
                } else {
                    // Fallback: derive from membersToSync entries with role 'Committee Member'
                    foreach ($membersToSync as $mid => $meta) {
                        if (!empty($meta['role']) && strtolower($meta['role']) === 'committee member') {
                            $desiredRegistryMemberIds[] = $mid;
                        }
                    }
                }

                if (!empty($desiredRegistryMemberIds)) {
                    $unique = array_values(array_unique($desiredRegistryMemberIds));
                    $registry->members()->sync($unique);
                } else {
                    // no desired registry members to sync
                }
            }
        }

        // If no registry id but sponsorship committee name provided, create or update a registry and sync its members
        if (empty($regId) && !empty($authorDetails) && !empty($authorDetails['sponsorship_committee']['name'])) {
            $scName = trim($authorDetails['sponsorship_committee']['name']);
            if (!empty($scName)) {
                $registry = CommitteeRegistry::firstOrCreate(['name' => $scName]);

                $memberIds = [];
                if (!empty($authorDetails['committee_members'])) {
                    foreach ($authorDetails['committee_members'] as $cm) {
                        if (empty($cm['name'])) continue;

                        $member = null;
                        if (!empty($cm['pmis_id'])) {
                            $employee = \App\Models\CityEmployee::with('department')->where('pmis_id', $cm['pmis_id'])->first();
                            if ($employee) {
                                $member = CommitteeMember::updateOrCreate(['pmis_id' => $employee->pmis_id], ['name' => $employee->full_name, 'position' => $employee->position, 'agency' => $employee->department->name ?? 'City Government']);
                            }
                        }

                        if (!$member) {
                            $member = CommitteeMember::firstOrCreate(['name' => $cm['name']], ['position' => 'External Partner']);
                        }

                        if ($member) $memberIds[] = $member->id;
                    }
                }

                // Sync registry members
                $registry->members()->sync($memberIds);

                // Remember this registry on the ordinance committee
                $committee->registry_id = $registry->id;
                $committee->save();

                // Also ensure these members are added to the committee sync
                foreach ($memberIds as $mid) {
                    $membersToSync[$mid] = ['role' => 'Committee Member'];
                }
            }
        }
            

        $committee->members()->sync($membersToSync);
    }

    public function storeIrr(Request $request, Ordinance $ordinance)
    {
        $request->validate([
            'status' => 'required|string',
            'lead_office_id' => 'required|exists:departments,id',
            'support_offices' => 'nullable|array',
            'external_institutions' => 'nullable|array',
            'external_institutions.members' => 'nullable|array',
            'external_institutions.ngos' => 'nullable|array',
            'external_institutions.others' => 'nullable|array',
            'file' => 'required|file|mimes:pdf|max:20480',
        ]);

        $path = $request->file('file')->store('irrs', 'public');

        DB::table('implementing_rules_and_regulations')->insert([
            'ordinance_id' => $ordinance->id,
            'lead_office_id' => $request->lead_office_id,
            'support_offices' => json_encode($request->support_offices ?? []),
            'external_institutions' => json_encode($request->external_institutions ?? ['members'=>[], 'ngos'=>[], 'others'=>[]]),
            'status' => $request->status,
            'is_active' => true,
            'file_path' => $path,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return redirect()->back()->with('success', 'IRR added successfully.');
    }

    public function disableIrr(Request $request, $id)
    {
        $request->validate([
            'reason' => 'required|string|max:1000'
        ]);

        DB::table('implementing_rules_and_regulations')->where('id', $id)->update([
            'is_active' => false,
            'disable_reason' => $request->reason,
            'updated_at' => now()
        ]);

        return redirect()->back()->with('success', 'IRR disabled successfully.');
    }

        public function getCommitteeMembers($id) 
        {
            $registry = \App\Models\CommitteeRegistry::with('members')->findOrFail($id);            
            return response()->json($registry->members->map(fn($m) => [
                'id' => $m->id,
                'pmis_id' => $m->pmis_id,
                'name' => $m->name
            ]));
        }
}