<?php

namespace App\Http\Controllers;

use App\Models\Department;
use App\Models\Ordinance;
use App\Models\CityEmployee; // <--- Added import
use App\Models\ExternalMember; // <--- Added import
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class OrdinanceController extends Controller
{
    public function index(Request $request)
    {
        // 1. Fetch Ordinances with relationships AND audits.user (History)
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

        $employees = CityEmployee::with('department')->where('state', 1)->get()->map(function($e) {
            return ['name' => $e->full_name, 'title' => ($e->position ?? 'Staff') . ($e->department ? ' (' . $e->department->name . ')' : ''), 'type' => 'Internal'];
        });
        $externals = ExternalMember::where('is_active', true)->get()->map(function($e) {
            return ['name' => $e->full_name, 'title' => $e->position . ($e->organization ? ' - ' . $e->organization : ''), 'type' => 'External'];
        });
        $peopleRegistry = $employees->concat($externals)->sortBy('name')->values()->toArray();

        return Inertia::render('ordinances/Index', [
            'ordinances' => $query->orderBy('id', 'desc')->paginate(10)->withQueryString(),
            'departments' => Department::orderBy('name')->get(),
            'statuses' => DB::table('statuses')->orderBy('name')->get(),
            'existing_ordinances' => Ordinance::select('id', 'ordinance_number', 'title')->orderBy('ordinance_number', 'desc')->get(),
            'peopleRegistry' => $peopleRegistry,
            'filters' => $request->only(['search']),
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
            'subject_matter' => 'nullable|string', // <--- ADDED
            'author_details' => 'nullable|array', // <--- ADDED
            'date_enacted' => 'required|date',
            'date_approved' => 'nullable|date',
            'effectivity_date' => 'nullable|date',
            'attested_by' => 'nullable|string',
            'approved_by' => 'nullable|string',
            'status_id' => 'required|exists:statuses,id',
            'file' => 'required|file|mimes:pdf|max:20480',
            
            // Logic Fields
            'amends_ordinance_id' => 'nullable|exists:ordinances,id',
            'relationship_type' => 'nullable|string|in:Amends,Repeals,Supersedes',
            'remarks' => 'nullable|string',
            
            // Array of department IDs (Removed sponsor_department_ids)
            'implementing_department_ids' => 'nullable|array',
            'is_active' => 'boolean', 
        ]);

        DB::transaction(function () use ($request, $validated) {
            
            // 1. AUTOMATION: Update Parent Status
            if (!empty($validated['amends_ordinance_id']) && !empty($validated['relationship_type'])) {
                $parent = Ordinance::find($validated['amends_ordinance_id']);
                $action = $validated['relationship_type'];
                
                if ($parent) {
                    if ($action === 'Amends') {
                        $parent->update([
                            'status_id' => DB::table('statuses')->where('name', 'Amended')->value('id')
                        ]);
                    } elseif ($action === 'Repeals') {
                        $parent->update([
                            'status_id' => DB::table('statuses')->where('name', 'Repealed')->value('id'),
                            'is_active' => false // Kills parent
                        ]);
                    } elseif ($action === 'Supersedes') {
                        $parent->update([
                            'status_id' => DB::table('statuses')->where('name', 'Superseded')->value('id'),
                            'is_active' => false // Kills parent
                        ]);
                    }
                }
            }

            // 2. Upload File
            $path = $request->file('file')->store('ordinances', 'public');

            // 3. Create Record
            $ordinance = Ordinance::create([
                'ordinance_number' => $validated['ordinance_number'],
                'title' => $validated['title'],
                'subject_matter' => $validated['subject_matter'] ?? null, // <--- ADDED
                'author_details' => $validated['author_details'] ?? null, // <--- ADDED
                'date_enacted' => $validated['date_enacted'],
                'date_approved' => $validated['date_approved'],
                'effectivity_date' => $validated['effectivity_date'],
                'attested_by' => $validated['attested_by'],
                'approved_by' => $validated['approved_by'],
                'status_id' => $validated['status_id'],
                'is_active' => $validated['is_active'] ?? true,
                'file_path' => $path,
                'amends_ordinance_id' => $validated['amends_ordinance_id'] ?? null,
                'relationship_type' => $validated['relationship_type'] ?? null,
                'remarks' => $validated['remarks'] ?? null,
            ]);

            // 4. Attach Offices (with Roles)
            $syncData = [];
            
            // Implementing
            if (!empty($validated['implementing_department_ids'])) {
                foreach ($validated['implementing_department_ids'] as $id) {
                    if (!isset($syncData[$id])) {
                        $syncData[$id] = ['role' => 'implementing'];
                    }
                }
            }
            
            $ordinance->departments()->sync($syncData);
        });

        return redirect()->back()->with('success', 'Ordinance encoded successfully.');
    }

    public function update(Request $request, Ordinance $ordinance)
    {
        $validated = $request->validate([
            // Identity
            'ordinance_number' => 'required|string|unique:ordinances,ordinance_number,' . $ordinance->id,
            'title' => 'required|string|max:1000',
            'subject_matter' => 'nullable|string', // <--- ADDED
            'author_details' => 'nullable|array', // <--- ADDED
            
            // Dates
            'date_enacted' => 'required|date',
            'date_approved' => 'nullable|date',
            'effectivity_date' => 'nullable|date',
            
            // Signatories
            'attested_by' => 'nullable|string',
            'approved_by' => 'nullable|string',
            
            // Status & File
            'status_id' => 'required|exists:statuses,id',
            'file' => 'nullable|file|mimes:pdf|max:20480', 
            
            // Logic Fields
            'amends_ordinance_id' => 'nullable|exists:ordinances,id',
            'relationship_type' => 'nullable|string|in:Amends,Repeals,Supersedes',
            'remarks' => 'nullable|string',
            
            // Offices (Removed sponsor_department_ids)
            'implementing_department_ids' => 'nullable|array',
            'is_active' => 'boolean', 
        ]);

        DB::transaction(function () use ($request, $validated, $ordinance) {
            
            // 1. AUTOMATION: Update Parent Status Logic
            if (!empty($validated['amends_ordinance_id']) && !empty($validated['relationship_type'])) {
                
                if ($ordinance->amends_ordinance_id != $validated['amends_ordinance_id'] || 
                    $ordinance->relationship_type != $validated['relationship_type']) {
                    
                    $parent = Ordinance::find($validated['amends_ordinance_id']);
                    $action = $validated['relationship_type'];
                    
                    if ($parent) {
                        if ($action === 'Amends') {
                            $parent->update([
                                'status_id' => DB::table('statuses')->where('name', 'Amended')->value('id')
                            ]);
                        } elseif ($action === 'Repeals') {
                            $parent->update([
                                'status_id' => DB::table('statuses')->where('name', 'Repealed')->value('id'),
                                'is_active' => false // Kills parent
                            ]);
                        } elseif ($action === 'Supersedes') {
                            $parent->update([
                                'status_id' => DB::table('statuses')->where('name', 'Superseded')->value('id'),
                                'is_active' => false // Kills parent
                            ]);
                        }
                    }
                }
            }

            // 2. Handle File Upload
            if ($request->hasFile('file')) {
                if ($ordinance->file_path && Storage::disk('public')->exists($ordinance->file_path)) {
                    Storage::disk('public')->delete($ordinance->file_path);
                }
                $ordinance->file_path = $request->file('file')->store('ordinances', 'public');
            }

            // 3. Update Record
            $ordinance->update([
                'ordinance_number' => $validated['ordinance_number'],
                'title' => $validated['title'],
                'subject_matter' => $validated['subject_matter'] ?? null, // <--- ADDED
                'author_details' => $validated['author_details'] ?? null, // <--- ADDED
                'date_enacted' => $validated['date_enacted'],
                'date_approved' => $validated['date_approved'],
                'effectivity_date' => $validated['effectivity_date'],
                'attested_by' => $validated['attested_by'],
                'approved_by' => $validated['approved_by'],
                'status_id' => $validated['status_id'],
                'is_active' => $validated['is_active'], 
                'amends_ordinance_id' => $validated['amends_ordinance_id'] ?? null,
                'relationship_type' => $validated['relationship_type'] ?? null,
                'remarks' => $validated['remarks'] ?? null,
            ]);

            // 4. Sync Offices
            $syncData = [];
            
            // Implementing
            if (!empty($validated['implementing_department_ids'])) {
                foreach ($validated['implementing_department_ids'] as $id) {
                    if (!isset($syncData[$id])) {
                        $syncData[$id] = ['role' => 'implementing'];
                    }
                }
            }
            
            $ordinance->departments()->sync($syncData);
        });

        return redirect()->back()->with('success', 'Ordinance updated successfully.');
    }
}