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
        $query = ExecutiveOrder::with(['status', 'departments', 'parentEO']);

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
        // We load these NOW so the modal works instantly without a second network call
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

    public function create()
    {
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
        ]);

        DB::transaction(function () use ($request, $validated) {
            // 1. Handle File Upload
            $path = $request->file('file')->store('eos', 'public');

            // 2. Create the EO Record
            $eo = ExecutiveOrder::create([
                'amends_eo_id' => $validated['amends_eo_id'] ?? null,
                'eo_number' => $validated['eo_number'],
                'title' => $validated['title'],
                'date_issued' => $validated['date_issued'],
                'effectivity_date' => $validated['effectivity_date'],
                'legal_basis' => $validated['legal_basis'],
                'issuing_authority' => 'City Mayor',
                'status_id' => $validated['status_id'],
                'file_path' => $path,
            ]);

            // 3. Attach Departments (The Pivot Logic)
            
            // Attach Lead Office (Role = lead)
            $eo->departments()->attach($validated['lead_office_id'], ['role' => 'lead']);

            // Attach Support Offices (Role = support)
            if (!empty($validated['support_office_ids'])) {
                // We map them to ensure every ID gets the 'support' role
                $supportData = [];
                foreach ($validated['support_office_ids'] as $id) {
                    // Prevent attaching the same office as Lead AND Support
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
        ]);

        DB::transaction(function () use ($request, $validated, $eo) {
            // Handle File Upload
            if ($request->hasFile('file')) {
                if ($eo->file_path && Storage::disk('public')->exists($eo->file_path)) {
                    Storage::disk('public')->delete($eo->file_path);
                }
                $eo->file_path = $request->file('file')->store('eos', 'public');
            }

            // 2. FIX: Save the 'amends_eo_id' here
            $eo->update([
                'amends_eo_id' => $validated['amends_eo_id'] ?? null, // <--- Add this line!
                'eo_number' => $validated['eo_number'],
                'title' => $validated['title'],
                'date_issued' => $validated['date_issued'],
                'effectivity_date' => $validated['effectivity_date'],
                'legal_basis' => $validated['legal_basis'],
                'status_id' => $validated['status_id'],
                // file_path is handled above or stays distinct
            ]);

            // Sync Departments
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