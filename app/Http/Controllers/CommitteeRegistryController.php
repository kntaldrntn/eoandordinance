<?php

namespace App\Http\Controllers;

use App\Models\CommitteeRegistry;
use App\Models\CommitteeMember;
use Illuminate\Http\Request;
use Inertia\Inertia;

class CommitteeRegistryController extends Controller
{
    public function index()
    {
        return Inertia::render('committee-registry/Index', [
            'committees' => CommitteeRegistry::with('members')->get(),
            'allMembers' => CommitteeMember::all(),
            'flash' => [
                'success' => session('success'),
                'error' => session('error')
            ],
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate(['name' => 'required|string|max:255']);
        CommitteeRegistry::create($data);
        return back()->with('success', 'Committee created successfully.');
    }

    // 🚀 NEW: Added Update Method
    public function update(Request $request, $id)
    {
        $data = $request->validate(['name' => 'required|string|max:255']);
        $registry = CommitteeRegistry::findOrFail($id);
        $registry->update($data);
        return back()->with('success', 'Committee updated successfully.');
    }

    // 🚀 NEW: Added Destroy Method
    public function destroy($id)
    {
        $registry = CommitteeRegistry::findOrFail($id);
        $registry->delete(); // This will cascade-delete the pivot table rows if your migration is set up correctly
        return back()->with('success', 'Committee deleted successfully.');
    }

    public function syncMembers(Request $request, $id)
    {
        $registry = CommitteeRegistry::findOrFail($id);
        
        // Ensure member_ids is always an array even if empty
        $memberIds = $request->input('member_ids', []);
        
        $registry->members()->sync($memberIds);
        return back()->with('success', 'Members updated successfully.');
    }
}