<?php

namespace App\Http\Controllers;

use App\Models\CommitteeMember;
use Illuminate\Http\Request;
use Inertia\Inertia;

class CommitteeMemberController extends Controller
{
    public function index(Request $request)
    {
        $query = CommitteeMember::query();

        if ($request->filled('search')) {
            $query->where('name', 'LIKE', "%{$request->search}%")
                  ->orWhere('position', 'LIKE', "%{$request->search}%");
        }

        return Inertia::render('committee-members/Index', [
            'members' => $query->orderBy('name')->paginate(15)->withQueryString(),
            'filters' => $request->only(['search']),
            'flash' => [
                'success' => session('success'),
                'error' => session('error')
            ]
        ]);
    }

    public function update(Request $request, $id) // 🚀 Changed parameter to just grab the $id
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'position' => 'nullable|string|max:255',
            'agency' => 'nullable|string|max:255',
        ]);

        // 🚀 Manually find the exact record using the ID, then update it
        $member = CommitteeMember::findOrFail($id);
        $member->update($validated);

        return redirect()->back()->with('success', 'Member updated successfully.');
    }
}