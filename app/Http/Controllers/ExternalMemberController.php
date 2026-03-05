<?php

namespace App\Http\Controllers;

use App\Models\ExternalMember;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ExternalMemberController extends Controller
{
    public function index(Request $request)
    {
        $query = ExternalMember::query();

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('full_name', 'LIKE', "%{$search}%")
                  ->orWhere('organization', 'LIKE', "%{$search}%")
                  ->orWhere('position', 'LIKE', "%{$search}%");
            });
        }

        return Inertia::render('externalmembers/Index', [
            'members' => $query->orderBy('full_name', 'asc')
                               ->paginate(10)
                               ->withQueryString(),
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
            'full_name'    => 'required|string|max:255',
            'position'     => 'nullable|string|max:255',
            'organization' => 'nullable|string|max:255',
            'is_active'    => 'boolean',
        ]);

        ExternalMember::create([
            'full_name'    => $validated['full_name'],
            'position'     => $validated['position'],
            'organization' => $validated['organization'],
            'is_active'    => $validated['is_active'] ?? true,
        ]);

        return redirect()->back()->with('success', 'External member added successfully.');
    }

    public function update(Request $request, ExternalMember $externalMember)
    {
        $validated = $request->validate([
            'full_name'    => 'required|string|max:255',
            'position'     => 'nullable|string|max:255',
            'organization' => 'nullable|string|max:255',
            'is_active'    => 'boolean',
        ]);

        $externalMember->update([
            'full_name'    => $validated['full_name'],
            'position'     => $validated['position'],
            'organization' => $validated['organization'],
            'is_active'    => $validated['is_active'],
        ]);

        return redirect()->back()->with('success', 'External member updated successfully.');
    }

    public function destroy(ExternalMember $externalMember)
    {
        $externalMember->delete();
        return redirect()->back()->with('success', 'External member removed successfully.');
    }
}