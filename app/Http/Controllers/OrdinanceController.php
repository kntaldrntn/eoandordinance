<?php

namespace App\Http\Controllers;

use App\Models\Department;
use App\Models\Ordinance;
use App\Models\CityEmployee;
use App\Models\ExternalMember;
use App\Models\Committee;
use App\Models\CommitteeMember;
use App\Models\CommitteeRegistry;
use App\Models\OrdinanceCode;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Spatie\PdfToText\Pdf;
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
            'committeeRegistries' => CommitteeRegistry::with('members')->get(),
            'ordinance_codes' => OrdinanceCode::orderBy('name')->get(),
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
            'ordinance_code_id' => 'nullable|exists:ordinance_codes,id',
        ]);

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

            // 🚀 PDF EXTRACTION LOGIC
            $path = null;
            $extractedText = null;

            if ($request->hasFile('file')) {
                $path = $request->file('file')->store('ordinances', 'public');
                
                try {
                    $absolutePath = storage_path('app/public/' . $path);
                    $extractedText = (new Pdf('C:/poppler/Library/bin/pdftotext.exe'))
                        ->setPdf($absolutePath)
                        ->text();
                } catch (\Exception $e) {
                    $extractedText = null; // Failsafe if it's an image-only PDF
                }
            }

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
                'document_content' => $extractedText,
                'amends_ordinance_id' => $validated['amends_ordinance_id'] ?? null,
                'relationship_type' => $validated['relationship_type'] ?? null,
                'remarks' => $validated['remarks'] ?? null,
                'ordinance_code_id' => $validated['ordinance_code_id'] ?? null,
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
            'date_enacted' => 'required|date|after_or_equal:date_enacted',
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
            'ordinance_code_id' => 'nullable|exists:ordinance_codes,id',
        ]);

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

            // 🚀 PDF EXTRACTION LOGIC FOR UPDATE
            $extractedText = $ordinance->document_content; // Default to keeping the old text

            if ($request->hasFile('file')) {
                // Delete old file if it exists
                if ($ordinance->file_path && Storage::disk('public')->exists($ordinance->file_path)) {
                    Storage::disk('public')->delete($ordinance->file_path);
                }
                
                // Store new file
                $path = $request->file('file')->store('ordinances', 'public');
                $ordinance->file_path = $path;

                // Extract text from the NEW file
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
            $isActive = !in_array($statusName, ['Suspended', 'Amended', 'Repealed', 'Repeal', 'Superseded', 'Supersede']);

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
                'document_content' => $extractedText, // 🚀 SAVE THE NEW EXTRACTED TEXT HERE
                'amends_ordinance_id' => $validated['amends_ordinance_id'] ?? null,
                'relationship_type' => $validated['relationship_type'] ?? null,
                'remarks' => $validated['remarks'] ?? null,
                'ordinance_code_id' => $validated['ordinance_code_id'] ?? null,
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
                $cleanName = trim($name);
                $member = \App\Models\CommitteeMember::whereRaw('LOWER(TRIM(name)) = ?', [strtolower($cleanName)])->first()
                    ?? \App\Models\CommitteeMember::create(['name' => $cleanName, 'position' => 'External Partner', 'agency' => null]);
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

        // Register Presiding/Attested/Approved persons as CommitteeMember entries
        $specials = [
            'Presiding Officer' => $ordinance->presiding_officer,
            'Attested By'       => $ordinance->attested_by,
            'Approved By'       => $ordinance->approved_by,
        ];

        foreach ($specials as $roleName => $personName) {
            if (empty($personName)) continue;

            $member = null;

            $employee = \App\Models\CityEmployee::where('full_name', $personName)->with('department')->first();
            if ($employee) {
                $member = CommitteeMember::updateOrCreate(
                    ['pmis_id' => $employee->pmis_id],
                    ['name' => $employee->full_name, 'position' => $employee->position, 'agency' => $employee->department->name ?? 'City Government']
                );
            }

            if (!$member) {
                $cleanName = trim($personName);
                $member = CommitteeMember::whereRaw('LOWER(TRIM(name)) = ?', [strtolower($cleanName)])->first()
                    ?? CommitteeMember::create(['name' => $cleanName, 'position' => 'External Partner']);
            }

            if ($member) {
                $membersToSync[$member->id] = ['role' => $roleName];
            }
        }

        // Determine registry id
        $regId = null;
        if (!empty($authorDetails) && !empty($authorDetails['selected_registry_id'])) {
            $regId = $authorDetails['selected_registry_id'];
        } elseif (request()->filled('selected_registry_id')) {
            $regId = request()->input('selected_registry_id');
        }

        // Clear sponsorship if explicitly removed
        if (!empty($authorDetails) && isset($authorDetails['sponsorship_committee'])) {
            $scName = trim($authorDetails['sponsorship_committee']['name'] ?? '');
            $scSelected = !empty($authorDetails['selected_registry_id']) || request()->filled('selected_registry_id');
            if ($scName === '' && !$scSelected) {
                $committee->registry_id = null;
                $committee->save();

                $existingCommitteeMemberIds = $committee->members()->wherePivot('role', 'Committee Member')->get()->pluck('id')->toArray();
                if (!empty($existingCommitteeMemberIds)) {
                    $committee->members()->detach($existingCommitteeMemberIds);
                }
            }
        }

        // Import from registry if registry id provided
        if (!empty($regId)) {
            $registry = CommitteeRegistry::with('members')->find($regId);
            if ($registry && $registry->members) {
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
                    $targetMemberId = null;

                    if (!empty($m->pmis_id)) {
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
                        $existingByNameId = $existingNames[strtolower(trim($m->name))] ?? null;
                        if ($existingByNameId) {
                            $targetMemberId = $existingByNameId;
                        } elseif (!empty($m->id)) {
                            $targetMemberId = $m->id;
                        } else {
                            $cleanName = trim($m->name);
                            $cm = CommitteeMember::whereRaw('LOWER(TRIM(name)) = ?', [strtolower($cleanName)])->first()
                                ?? CommitteeMember::create(['name' => $cleanName, 'position' => 'External Partner']);
                            $targetMemberId = $cm->id;
                        }
                    }

                    if (!empty($m->pmis_id) && isset($existingPmis[$m->pmis_id])) {
                        unset($membersToSync[$existingPmis[$m->pmis_id]]);
                    }
                    if (isset($existingNames[strtolower(trim($m->name))])) {
                        $eid = $existingNames[strtolower(trim($m->name))];
                        if (isset($membersToSync[$eid])) unset($membersToSync[$eid]);
                    }

                    if ($targetMemberId) $membersToSync[$targetMemberId] = ['role' => 'Committee Member'];
                }

                $committee->registry_id = $registry->id;
                $committee->save();

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

                        if (!$member && !empty($cm['id'])) {
                            $member = CommitteeMember::find($cm['id']);
                        }

                        if (!$member) {
                            $cleanName = trim($cm['name']);
                            $member = CommitteeMember::whereRaw('LOWER(TRIM(name)) = ?', [strtolower($cleanName)])->first()
                                ?? CommitteeMember::create(['name' => $cleanName, 'position' => $cm['position'] ?? 'External Partner']);
                        }

                        if ($member) $desiredRegistryMemberIds[] = $member->id;
                    }
                } else {
                    foreach ($membersToSync as $mid => $meta) {
                        if (!empty($meta['role']) && strtolower($meta['role']) === 'committee member') {
                            $desiredRegistryMemberIds[] = $mid;
                        }
                    }
                }

                if (!empty($desiredRegistryMemberIds)) {
                    $registry->members()->sync(array_values(array_unique($desiredRegistryMemberIds)));
                }
            }
        }

        // No registry id but sponsorship committee name provided
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
                                $member = CommitteeMember::updateOrCreate(
                                    ['pmis_id' => $employee->pmis_id],
                                    ['name' => $employee->full_name, 'position' => $employee->position, 'agency' => $employee->department->name ?? 'City Government']
                                );
                            }
                        }

                        if (!$member) {
                            $cleanName = trim($cm['name']);
                            $member = CommitteeMember::whereRaw('LOWER(TRIM(name)) = ?', [strtolower($cleanName)])->first()
                                ?? CommitteeMember::create(['name' => $cleanName, 'position' => 'External Partner']);
                        }

                        if ($member) $memberIds[] = $member->id;
                    }
                }

                $registry->members()->sync($memberIds);
                $committee->registry_id = $registry->id;
                $committee->save();

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
        $extractedText = null;

        try {
            $absolutePath = storage_path('app/public/' . $path);
            $extractedText = (new Pdf('C:/poppler/Library/bin/pdftotext.exe'))
                ->setPdf($absolutePath)
                ->text();
        } catch (\Exception $e) {
            $extractedText = null;  
        }

        DB::table('implementing_rules_and_regulations')->insert([
            'ordinance_id' => $ordinance->id,
            'lead_office_id' => $request->lead_office_id,
            'support_offices' => json_encode($request->support_offices ?? []),
            'external_institutions' => json_encode($request->external_institutions ?? ['members' => [], 'ngos' => [], 'others' => []]),
            'status' => $request->status,
            'is_active' => true,
            'document_content' => $extractedText,
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