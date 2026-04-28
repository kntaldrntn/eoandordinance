<?php

namespace App\Http\Controllers;

use App\Models\Department;
use App\Models\ExecutiveOrder;
use App\Models\CityEmployee;
use App\Models\ExternalMember;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class EOController extends Controller
{
    public function index(Request $request)
    {
        $query = ExecutiveOrder::with([
            'status', 
            'departments', 
            'parentEO', 
            'implementingRules.leadOffice', 
            'amendments', 
            'audits.user'
        ]);

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('eo_number', 'LIKE', "%{$search}%")
                  ->orWhere('title', 'LIKE', "%{$search}%");
            });
        }

        $eos = $query->orderBy('id', 'desc')->paginate(10)->withQueryString();
                     
        $employees = CityEmployee::with('department')->where('state', 1)->get()->map(function($e) {
            return [
                'name' => $e->full_name,
                'title' => ($e->position ?? 'Staff') . ($e->department ? ' (' . $e->department->name . ')' : ''),
                'type' => 'Internal'
            ];
        });

        $externals = ExternalMember::where('is_active', true)->get()->map(function($e) {
            return [
                'name' => $e->full_name,
                'title' => $e->position . ($e->organization ? ' - ' . $e->organization : ''),
                'type' => 'External'
            ];
        });

        $peopleRegistry = $employees->concat($externals)->sortBy('name')->values()->toArray();

        return Inertia::render('eo/Index', [
            'eos' => $eos,
            'departments' => Department::orderBy('name')->get(),
            'peopleRegistry' => $peopleRegistry, 
            'statuses' => DB::table('statuses')->orderBy('id')->get(),
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
            'title' => 'required|string|max:1000',
            'classification' => 'nullable|string',
            'date_issued' => 'required|date',
            'effectivity_date' => 'nullable|date|after_or_equal:date_issued',
            'legal_basis' => 'nullable|string',
            'lead_office_id' => 'nullable|exists:departments,id',
            'support_office_ids' => 'nullable|array',
            'status_id' => 'required|exists:statuses,id',
            'file' => 'nullable|file|mimes:pdf|max:20480',
            'is_active' => 'boolean', 
            'committee_details' => 'nullable|array', 
        ]);

        DB::transaction(function () use ($request, $validated) {
            
            // REVERSE LOGIC: Update the OLD parent EO to "Amended"
            if (!empty($validated['amends_eo_id'])) {
                $oldEO = ExecutiveOrder::find($validated['amends_eo_id']);
                $amendedStatus = DB::table('statuses')->where('name', 'Amended')->first();
                $amendedStatusId = $amendedStatus ? $amendedStatus->id : DB::table('statuses')->insertGetId(['name' => 'Amended']);

                if ($oldEO) {
                    $oldEO->update([
                        'status_id' => $amendedStatusId, 
                        'is_active' => false 
                    ]);
                }
            }

            $path = $request->hasFile('file') ? $request->file('file')->store('eos', 'public') : null;

            $eo = ExecutiveOrder::create([
                'amends_eo_id' => $validated['amends_eo_id'] ?? null,
                'relationship_type' => !empty($validated['amends_eo_id']) ? 'Amends' : null,
                'eo_number' => $validated['eo_number'],
                'title' => $validated['title'],
                'classification' => $validated['classification'] ?? null,
                'date_issued' => $validated['date_issued'],
                'effectivity_date' => $validated['effectivity_date'],
                'legal_basis' => $validated['legal_basis'],
                'issuing_authority' => 'City Mayor',
                'status_id' => $validated['status_id'], 
                'is_active' => $validated['is_active'] ?? true, 
                'committee_details' => $validated['committee_details'] ?? null,
                'file_path' => $path,
            ]);

            if (!empty($validated['lead_office_id'])) {
                $eo->departments()->attach($validated['lead_office_id'], ['role' => 'lead']);
            }

            if (!empty($validated['support_office_ids'])) {
                $supportData = [];
                foreach ($validated['support_office_ids'] as $id) {
                    if ($id != ($validated['lead_office_id'] ?? 0)) {
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
            'title' => 'required|string|max:1000',
            'classification' => 'nullable|string',
            'date_issued' => 'required|date',
            'effectivity_date' => 'nullable|date|after_or_equal:date_issued',
            'legal_basis' => 'nullable|string',
            'lead_office_id' => 'nullable|exists:departments,id',
            'support_office_ids' => 'nullable|array',
            'status_id' => 'required|exists:statuses,id',
            'file' => 'nullable|file|mimes:pdf|max:20480',
            'is_active' => 'boolean',
            'committee_details' => 'nullable|array', 
        ]);

        DB::transaction(function () use ($request, $validated, $eo) {
            
            // Handle Parent logic on Update as well
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

            $eo->update([
                'amends_eo_id' => $validated['amends_eo_id'] ?? null,
                'relationship_type' => !empty($validated['amends_eo_id']) ? 'Amends' : null,
                'eo_number' => $validated['eo_number'],
                'title' => $validated['title'],
                'classification' => $validated['classification'] ?? null,
                'date_issued' => $validated['date_issued'],
                'effectivity_date' => $validated['effectivity_date'],
                'legal_basis' => $validated['legal_basis'],
                'status_id' => $validated['status_id'],
                'is_active' => $validated['is_active'], 
                'committee_details' => $validated['committee_details'] ?? null, 
            ]);

            $eo->departments()->detach();
            if (!empty($validated['lead_office_id'])) {
                $eo->departments()->attach($validated['lead_office_id'], ['role' => 'lead']);
            }
            if (!empty($validated['support_office_ids'])) {
                $supportData = [];
                foreach ($validated['support_office_ids'] as $id) {
                    if ($id != ($validated['lead_office_id'] ?? 0)) {
                        $supportData[$id] = ['role' => 'support'];
                    }
                }
                $eo->departments()->attach($supportData);
            }
        });

        return redirect()->back()->with('success', 'Executive Order updated successfully.');
    }
}