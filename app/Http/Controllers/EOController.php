<?php

namespace App\Http\Controllers;

use App\Models\Department;
use App\Models\ExecutiveOrder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class EOController extends Controller
{
    public function index(Request $request)
    {
        // 1. Fetch the EOs with their relationships for the Table
        $query = ExecutiveOrder::with(['status', 'departments', 'parentEO', 'implementingRules.leadOffice', 'amendments']);

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('eo_number', 'LIKE', "%{$search}%")
                  ->orWhere('title', 'LIKE', "%{$search}%");
            });
        }

        $eos = $query->orderBy('date_issued', 'desc')
                     ->paginate(10)
                     ->withQueryString();

        // 2. Fetch Data for the Modal Dropdowns
        $departments = Department::orderBy('name')->get();
        $statuses = DB::table('statuses')->orderBy('name')->get();

        return Inertia::render('eo/Index', [
            'eos' => $eos,
            'departments' => $departments,
            'statuses' => $statuses,
            'existing_eos' => ExecutiveOrder::select('id', 'eo_number', 'title')->orderBy('eo_number', 'desc')->get(),
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
            'amends_eo_id' => 'nullable|exists:executive_orders,id',
            'eo_number' => 'required|string|unique:executive_orders,eo_number',
            'title' => 'required|string|max:500',
            'date_issued' => 'required|date',
            'effectivity_date' => 'nullable|date',
            'legal_basis' => 'nullable|string',
            'lead_office_id' => 'required|exists:departments,id',
            'support_office_ids' => 'nullable|array',
            'support_office_ids.*' => 'exists:departments,id',
            'status_id' => 'required|exists:statuses,id',
            'file' => 'required|file|mimes:pdf|max:10240',
            // NEW VALIDATIONS
            'relationship_type' => 'nullable|string|in:Amends,Repeals,Supplements',
            'remarks' => 'nullable|string',
        ]);

        DB::transaction(function () use ($request, $validated) {

            // 1. AUTOMATION: Update Parent Status Logic
            if (!empty($validated['amends_eo_id']) && !empty($validated['relationship_type'])) {
                
                $parentEO = ExecutiveOrder::find($validated['amends_eo_id']);
                $action = $validated['relationship_type'];

                if ($parentEO) {
                    if ($action === 'Amends') {
                        $statusId = DB::table('statuses')->where('name', 'Amended')->value('id'); 
                        $parentEO->update(['status_id' => $statusId]);
                    } 
                    elseif ($action === 'Repeals') {
                        $statusId = DB::table('statuses')->where('name', 'Repealed')->value('id'); 
                        $parentEO->update(['status_id' => $statusId]);
                    }
                    elseif ($action === 'Supplements') {
                        $activeId = DB::table('statuses')->where('name', 'Active')->value('id'); 
                        if ($parentEO->status_id != $activeId) {
                             $parentEO->update(['status_id' => $activeId]);
                        }
                    }
                }
            }

            // 2. Handle File Upload
            $path = $request->file('file')->store('eos', 'public');

            // 3. Create the EO Record
            $eo = ExecutiveOrder::create([
                'amends_eo_id' => $validated['amends_eo_id'] ?? null,
                'relationship_type' => $validated['relationship_type'] ?? null, // SAVE THIS
                'remarks' => $validated['remarks'] ?? null,                     // SAVE THIS
                'eo_number' => $validated['eo_number'],
                'title' => $validated['title'],
                'date_issued' => $validated['date_issued'],
                'effectivity_date' => $validated['effectivity_date'],
                'legal_basis' => $validated['legal_basis'],
                'issuing_authority' => 'City Mayor',
                'status_id' => $validated['status_id'],
                'file_path' => $path,
            ]);

            // 4. Attach Departments
            $eo->departments()->attach($validated['lead_office_id'], ['role' => 'lead']);

            if (!empty($validated['support_office_ids'])) {
                $supportData = [];
                foreach ($validated['support_office_ids'] as $id) {
                    if ($id != $validated['lead_office_id']) {
                        $supportData[$id] = ['role' => 'support'];
                    }
                }
                $eo->departments()->attach($supportData);
            }
        });

        return redirect()->back()->with('success', 'Executive Order encoded successfully.');
    }

    public function update(Request $request, ExecutiveOrder $eo)
    {
        $validated = $request->validate([
            'amends_eo_id' => 'nullable|exists:executive_orders,id',
            'eo_number' => 'required|string|unique:executive_orders,eo_number,' . $eo->id, 
            'title' => 'required|string|max:500',
            'date_issued' => 'required|date',
            'effectivity_date' => 'nullable|date',
            'legal_basis' => 'nullable|string',
            'lead_office_id' => 'required|exists:departments,id',
            'support_office_ids' => 'nullable|array',
            'status_id' => 'required|exists:statuses,id',
            'file' => 'nullable|file|mimes:pdf|max:10240',
            // NEW VALIDATIONS FOR UPDATE
            'relationship_type' => 'nullable|string|in:Amends,Repeals,Supplements',
            'remarks' => 'nullable|string',
        ]);

        DB::transaction(function () use ($request, $validated, $eo) {
            
            // 1. AUTOMATION: Update Parent Status on Edit
            // Only run if relationship info is present
            if (!empty($validated['amends_eo_id']) && !empty($validated['relationship_type'])) {
                
                // Logic: If the user changed the parent OR the relationship type, trigger the update on the (new) parent
                if ($eo->amends_eo_id != $validated['amends_eo_id'] || $eo->relationship_type != $validated['relationship_type']) {
                    
                    $parentEO = ExecutiveOrder::find($validated['amends_eo_id']);
                    $action = $validated['relationship_type'];

                    if ($parentEO) {
                        if ($action === 'Amends') {
                            $statusId = DB::table('statuses')->where('name', 'Amended')->value('id');
                            $parentEO->update(['status_id' => $statusId]);
                        } 
                        elseif ($action === 'Repeals') {
                            $statusId = DB::table('statuses')->where('name', 'Repealed')->value('id');
                            $parentEO->update(['status_id' => $statusId]);
                        }
                    }
                }
            }

            // 2. Handle File Upload
            if ($request->hasFile('file')) {
                if ($eo->file_path && Storage::disk('public')->exists($eo->file_path)) {
                    Storage::disk('public')->delete($eo->file_path);
                }
                $eo->file_path = $request->file('file')->store('eos', 'public');
            }

            // 3. Update EO Record
            $eo->update([
                'amends_eo_id' => $validated['amends_eo_id'] ?? null,
                'relationship_type' => $validated['relationship_type'] ?? null, // UPDATE THIS
                'remarks' => $validated['remarks'] ?? null,                     // UPDATE THIS
                'eo_number' => $validated['eo_number'],
                'title' => $validated['title'],
                'date_issued' => $validated['date_issued'],
                'effectivity_date' => $validated['effectivity_date'],
                'legal_basis' => $validated['legal_basis'],
                'status_id' => $validated['status_id'],
            ]);

            // 4. Sync Departments
            $eo->departments()->detach();
            $eo->departments()->attach($validated['lead_office_id'], ['role' => 'lead']);

            if (!empty($validated['support_office_ids'])) {
                $supportData = [];
                foreach ($validated['support_office_ids'] as $id) {
                    if ($id != $validated['lead_office_id']) {
                        $supportData[$id] = ['role' => 'support'];
                    }
                }
                $eo->departments()->attach($supportData);
            }
        });

        return redirect()->back()->with('success', 'Executive Order updated successfully.');
    }
}