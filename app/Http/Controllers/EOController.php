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
        // Automatically ensure our 3 specific statuses exist
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
            'classifications' => DB::table('classifications')->orderBy('name')->get(), 
            'existing_eos' => ExecutiveOrder::select('id', 'eo_number', 'title', 'is_active', 'effectivity_date')
            ->where('is_active', true)
            ->orderBy('eo_number', 'desc')
            ->get(),
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
            'classification_id' => 'nullable|exists:classifications,id', 
            'date_issued' => 'required|date',
            'effectivity_date' => 'nullable|date|after_or_equal:date_issued',
            'legal_basis' => 'nullable|string',
            'lead_office_id' => 'nullable|exists:departments,id',
            'status_id' => 'required|exists:statuses,id',
            'file' => 'nullable|file|mimes:pdf|max:20480',
            // 'is_active' validation removed
            'committee_details' => 'nullable|array', 
            'declaration' => 'nullable|string', 
        ]);

        DB::transaction(function () use ($request, $validated) {
            
            // Handle Parent EO if this is an amendment
            if (!empty($validated['amends_eo_id'])) {
                $oldEO = ExecutiveOrder::find($validated['amends_eo_id']);
                $amendedStatus = DB::table('statuses')->where('name', 'Amended')->first();
                $amendedStatusId = $amendedStatus ? $amendedStatus->id : DB::table('statuses')->insertGetId(['name' => 'Amended']);

                if ($oldEO) {
                    $oldEO->update([
                        'status_id' => $amendedStatusId, 
                        'is_active' => false // Old EO becomes inactive
                    ]);
                }
            }

            $path = $request->hasFile('file') ? $request->file('file')->store('eos', 'public') : null;

            // 🚀 Determine active status automatically based on the selected Status
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
                'is_active' => $isActive, // 🚀 Saved automatically
                'committee_details' => $validated['committee_details'] ?? null,
                'declaration' => $validated['declaration'] ?? null,
                'file_path' => $path,
            ]);

            if (!empty($validated['lead_office_id'])) {
                $eo->departments()->attach($validated['lead_office_id'], ['role' => 'lead']);
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
            // 'is_active' validation removed
            'committee_details' => 'nullable|array', 
            'declaration' => 'nullable|string', 
        ]);

        DB::transaction(function () use ($request, $validated, $eo) {
            
            // Handle Parent EO logic
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

            // 🚀 Determine active status automatically based on the selected Status
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
                'is_active' => $isActive,  // 🚀 Saved automatically
                'committee_details' => $validated['committee_details'] ?? null, 
                'declaration' => $validated['declaration'] ?? null,
            ]);

            $eo->departments()->detach();
            if (!empty($validated['lead_office_id'])) {
                $eo->departments()->attach($validated['lead_office_id'], ['role' => 'lead']);
            }
        });

        return redirect()->back()->with('success', 'Executive Order updated successfully.');
    }
}