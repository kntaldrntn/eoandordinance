<?php

namespace App\Http\Controllers;

use App\Models\Department;
use App\Models\Ordinance;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class OrdinanceController extends Controller
{
    public function index(Request $request)
    {
        $query = Ordinance::with(['status', 'departments', 'parentOrdinance', 'amendments']);

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('ordinance_number', 'LIKE', "%{$search}%")
                  ->orWhere('title', 'LIKE', "%{$search}%");
            });
        }

        return Inertia::render('ordinance/Index', [
            'ordinances' => $query->orderBy('date_enacted', 'desc')->paginate(10)->withQueryString(),
            'departments' => Department::orderBy('name')->get(),
            'statuses' => DB::table('statuses')->orderBy('name')->get(),
            'existing_ordinances' => Ordinance::select('id', 'ordinance_number', 'title')->orderBy('ordinance_number', 'desc')->get(),
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
            'date_enacted' => 'required|date',
            'date_approved' => 'nullable|date',
            'effectivity_date' => 'nullable|date',
            'attested_by' => 'nullable|string',
            'approved_by' => 'nullable|string',
            'status_id' => 'required|exists:statuses,id',
            'file' => 'required|file|mimes:pdf|max:20480', // 20MB limit
            
            // Logic Fields
            'amends_ordinance_id' => 'nullable|exists:ordinances,id',
            'relationship_type' => 'nullable|string|in:Amends,Repeals,Supersedes',
            'remarks' => 'nullable|string',
            
            // Array of department IDs
            'sponsor_department_ids' => 'nullable|array', 
            'implementing_department_ids' => 'nullable|array',
        ]);

        DB::transaction(function () use ($request, $validated) {
            
            // 1. AUTOMATION: Update Parent Status
            if (!empty($validated['amends_ordinance_id']) && !empty($validated['relationship_type'])) {
                $parent = Ordinance::find($validated['amends_ordinance_id']);
                $action = $validated['relationship_type'];
                
                if ($parent) {
                    if ($action === 'Amends') {
                        $parent->update(['status_id' => DB::table('statuses')->where('name', 'Amended')->value('id')]);
                    } elseif ($action === 'Repeals') {
                        $parent->update(['status_id' => DB::table('statuses')->where('name', 'Repealed')->value('id')]);
                    } elseif ($action === 'Supersedes') {
                        $parent->update(['status_id' => DB::table('statuses')->where('name', 'Superseded')->value('id')]);
                    }
                }
            }

            // 2. Upload File
            $path = $request->file('file')->store('ordinances', 'public');

            // 3. Create Record
            $ordinance = Ordinance::create([
                'ordinance_number' => $validated['ordinance_number'],
                'title' => $validated['title'],
                'date_enacted' => $validated['date_enacted'],
                'date_approved' => $validated['date_approved'],
                'effectivity_date' => $validated['effectivity_date'],
                'attested_by' => $validated['attested_by'],
                'approved_by' => $validated['approved_by'],
                'status_id' => $validated['status_id'],
                'file_path' => $path,
                'amends_ordinance_id' => $validated['amends_ordinance_id'] ?? null,
                'relationship_type' => $validated['relationship_type'] ?? null,
                'remarks' => $validated['remarks'] ?? null,
            ]);

            // 4. Attach Offices (with Roles)
            $syncData = [];
            
            // Sponsors
            if (!empty($validated['sponsor_department_ids'])) {
                foreach ($validated['sponsor_department_ids'] as $id) {
                    $syncData[$id] = ['role' => 'sponsor'];
                }
            }
            // Implementing
            if (!empty($validated['implementing_department_ids'])) {
                foreach ($validated['implementing_department_ids'] as $id) {
                    // Avoid duplicates if same office is both
                    if (!isset($syncData[$id])) {
                        $syncData[$id] = ['role' => 'implementing'];
                    }
                }
            }
            
            $ordinance->departments()->sync($syncData);
        });

        return redirect()->back()->with('success', 'Ordinance encoded successfully.');
    }
}