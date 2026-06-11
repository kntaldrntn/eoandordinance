<?php

namespace App\Http\Controllers;

use App\Models\Department;
use App\Models\User;
use App\Models\CityEmployee;
use App\Models\CommitteeMember;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules;
use Inertia\Inertia;

class UserController extends Controller
{
    public function index(Request $request)
    {
        $query = User::with(['department', 'committeeMember']);

        if ($request->filled('search')) {
            $searchTerm = $request->search;
            $query->where(function ($q) use ($searchTerm) {
                $q->where('name', 'LIKE', "%{$searchTerm}%")
                  ->orWhere('email', 'LIKE', "%{$searchTerm}%");
            });
        }

        $users = $query->orderBy('id', 'desc')->paginate(10)->withQueryString();
        $departments = Department::orderBy('name')->get();

        // Kept for backward compatibility / other suggestions if needed
        $employees = CityEmployee::where('state', 1)
            ->select('id', 'pmis_id', 'full_name', 'position')
            ->orderBy('full_name')
            ->get();

        // 🚀 NEW: All registered committee members (internal + external)
        // for linking a User account to their committee membership records
        $committeeMembers = CommitteeMember::select('id', 'pmis_id', 'name', 'position', 'agency')
            ->orderBy('name')
            ->get();

        return Inertia::render('users/Index', [
            'users' => $users,
            'departments' => $departments,
            'employees' => $employees,
            'committeeMembers' => $committeeMembers,
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
            'pmis_id' => 'nullable|integer',
            'committee_member_id' => 'nullable|exists:committee_members,id',
            'name' => 'required|string|max:255',
            'email' => 'required|string|lowercase|email|max:255|unique:' . User::class,
            'role' => ['required', Rule::in(['system_admin', 'supervisor', 'focal_person', 'monitoring_committee', 'read_only'])],
            'department_id' => 'nullable|exists:departments,id',
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ]);

        User::create([
            'pmis_id' => $validated['pmis_id'] ?? null,
            'committee_member_id' => $validated['committee_member_id'] ?? null,
            'name' => $validated['name'],
            'email' => $validated['email'],
            'role' => $validated['role'],
            'department_id' => $validated['department_id'],
            'password' => Hash::make($validated['password']),
            'email_verified_at' => now(),
        ]);

        return redirect()->back()->with('success', 'User created successfully.');
    }

    public function update(Request $request, User $user)
    {
        $validated = $request->validate([
            'pmis_id' => 'nullable|integer',
            'committee_member_id' => 'nullable|exists:committee_members,id',
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255|unique:users,email,' . $user->id,
            'role' => ['required', Rule::in(['system_admin', 'supervisor', 'focal_person', 'monitoring_committee', 'read_only'])],
            'department_id' => 'nullable|exists:departments,id',
        ]);

        if ($request->filled('password')) {
            $request->validate([
                'password' => ['confirmed', Rules\Password::defaults()]
            ]);
            $user->password = Hash::make($request->password);
        }

        $user->fill($validated);
        $user->save();

        return redirect()->back()->with('success', 'User updated successfully.');
    }

    public function destroy(User $user)
    {
        $user->delete();
        return redirect()->back()->with('success', 'User deleted successfully.');
    }
}