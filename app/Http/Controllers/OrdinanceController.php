<?php

namespace App\Http\Controllers;

use App\Models\Department;
use App\Models\Ordinance;
use App\Models\CityEmployee; 
use App\Models\ExternalMember; 
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class OrdinanceController extends Controller
{
    public function index(Request $request)
    {
        // Ensure standard statuses exist
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
            'implementingRules.leadOffice', 
            'audits.user' 
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
            return ['name' => $e->full_name, 'title' => ($e->position ?? 'Staff') . ($e->department ? ' (' . $e->department->name . ')' : ''), 'type' => 'Internal'];
        });
        
        $externals = ExternalMember::where('is_active', true)->get()->map(function($e) {
            return ['name' => $e->full_name, 'title' => $e->position . ($e->organization ? ' - ' . $e->organization : ''), 'type' => 'External'];
        });
        
        $peopleRegistry = $employees->concat($externals)->sortBy('name')->values()->toArray();

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
            
            'author_details' => 'nullable|array', 
            
            // Validate nested tabs
            'external_institutions' => 'nullable|array', 
            'external_institutions.members' => 'nullable|array',
            'external_institutions.ngos' => 'nullable|array',
            'external_institutions.others' => 'nullable|array',
            
            'lead_office_id' => 'nullable|exists:departments,id',
            'support_office_ids' => 'nullable|array',
            // 'is_active' validation removed
        ]);

        DB::transaction(function () use ($request, $validated) {
            
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

            // 🚀 Determine active status automatically based on the selected Status
            $statusName = DB::table('statuses')->where('id', $validated['status_id'])->value('name');
            $isActive = !in_array($statusName, ['Suspended', 'Amended', 'Repealed', 'Repeal', 'Superseded', 'Supersede']);

            $ordinance = Ordinance::create([
                'ordinance_number' => $validated['ordinance_number'],
                'title' => $validated['title'],
                'subject_matter' => $validated['subject_matter'] ?? null, 
                
                'author_details' => $validated['author_details'] ?? [], 
                'external_institutions' => $validated['external_institutions'] ?? ['members'=>[], 'ngos'=>[], 'others'=>[]], 
                
                'date_enacted' => $validated['date_enacted'],
                'date_approved' => $validated['date_approved'],
                'effectivity_date' => $validated['effectivity_date'],
                'presiding_officer' => $validated['presiding_officer'],
                'attested_by' => $validated['attested_by'] ?? null,
                'approved_by' => $validated['approved_by'] ?? null,     
                'status_id' => $validated['status_id'],
                'is_active' => $isActive, // 🚀 Saved automatically
                'file_path' => $path,
                'amends_ordinance_id' => $validated['amends_ordinance_id'] ?? null,
                'relationship_type' => $validated['relationship_type'] ?? null,
                'remarks' => $validated['remarks'] ?? null,
            ]);

            // Unified Lead and Support Office Sync
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
            
            'author_details' => 'nullable|array', 
            
            // Validate nested tabs
            'external_institutions' => 'nullable|array', 
            'external_institutions.members' => 'nullable|array',
            'external_institutions.ngos' => 'nullable|array',
            'external_institutions.others' => 'nullable|array',
            
            'lead_office_id' => 'nullable|exists:departments,id',
            'support_office_ids' => 'nullable|array',
            // 'is_active' validation removed
        ]);

        DB::transaction(function () use ($request, $validated, $ordinance) {
            
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
                            
                            // 🚀 FIX: Match the exact spelling in your database
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

            // 🚀 Determine active status automatically based on the selected Status
            $statusName = DB::table('statuses')->where('id', $validated['status_id'])->value('name');
            $isActive = !in_array($statusName, ['Suspended','Amended', 'Repealed', 'Repeal', 'Superseded', 'Supersede']);

            $ordinance->update([
                'ordinance_number' => $validated['ordinance_number'],
                'title' => $validated['title'],
                'subject_matter' => $validated['subject_matter'] ?? null, 
                
                'author_details' => $validated['author_details'] ?? [], 
                'external_institutions' => $validated['external_institutions'] ?? ['members'=>[], 'ngos'=>[], 'others'=>[]], 
                
                'date_enacted' => $validated['date_enacted'],
                'date_approved' => $validated['date_approved'],
                'effectivity_date' => $validated['effectivity_date'],
                'presiding_officer' => $validated['presiding_officer'],
                'attested_by' => $validated['attested_by'] ?? null,
                'approved_by' => $validated['approved_by'] ?? null,
                'status_id' => $validated['status_id'],
                'is_active' => $isActive, // 🚀 Saved automatically
                'amends_ordinance_id' => $validated['amends_ordinance_id'] ?? null,
                'relationship_type' => $validated['relationship_type'] ?? null,
                'remarks' => $validated['remarks'] ?? null,
            ]);

            // Unified Lead and Support Office Sync
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
        });

        return redirect()->back()->with('success', 'Ordinance updated successfully.');
    }

    // --- IRR MANAGEMENT LOGIC ---

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
            
            // 🚀 Properly saves the nested object
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
}