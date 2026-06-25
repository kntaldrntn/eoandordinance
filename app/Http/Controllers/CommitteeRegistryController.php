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
        
        // This is the array of IDs in the exact order you clicked them
        $memberIds = $request->input('member_ids', []);
        
        $syncData = [];
        foreach (array_values($memberIds) as $index => $memberId) {
            if ($index === 0) {
                $role = 'Chairperson'; // 🚀 FIXED to match Vue
            } elseif ($index === 1) {
                $role = 'Vice Chairperson'; // 🚀 FIXED to match Vue
            } else {
                $role = 'Member';
            }
            
            // Attach the role directly to the pivot record
            $syncData[$memberId] = ['role' => $role];
        }
        
        $registry->members()->sync($syncData);
        return back()->with('success', 'Members and roles updated successfully.');
    }
}