<?php

namespace App\Http\Controllers;

use App\Models\CityEmployee;
use App\Models\Department;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class CityEmployeeController extends Controller
{
    public function index(Request $request)
    {
        $query = CityEmployee::with('department');

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('full_name', 'LIKE', "%{$search}%")
                  ->orWhere('pmis_id', 'LIKE', "%{$search}%")
                  ->orWhere('position', 'LIKE', "%{$search}%");
            });
        }

        return Inertia::render('employees/Index', [
            'employees' => $query->orderBy('full_name', 'asc')
                                 ->paginate(10)
                                 ->withQueryString(),
            'departments' => Department::orderBy('name')->get(),
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
            'pmis_id'   => 'required|string|unique:city_employees,pmis_id',
            'full_name' => 'required|string|max:255',
            'position'  => 'nullable|string|max:255',
            'dept_id'   => 'required|exists:departments,id',
            'state'     => 'boolean',
        ]);

        CityEmployee::create([
            'pmis_id'   => $validated['pmis_id'],
            'full_name' => $validated['full_name'],
            'position'  => $validated['position'],
            'dept_id'   => $validated['dept_id'],
            'state'     => $validated['state'] ? 1 : 0,
        ]);

        return redirect()->back()->with('success', 'Employee added successfully.');
    }

    public function update(Request $request, $id)
    {
        $employee = CityEmployee::findOrFail($id);

        $validated = $request->validate([
            'pmis_id'   => ['required', 'string', Rule::unique('city_employees', 'pmis_id')->ignore($employee->pmis_id, 'pmis_id')],
            'full_name' => 'required|string|max:255',
            'position'  => 'nullable|string|max:255',
            'dept_id'   => 'required|exists:departments,id',
            'state'     => 'boolean',
        ]);

        $employee->update([
            'pmis_id'   => $validated['pmis_id'],
            'full_name' => $validated['full_name'],
            'position'  => $validated['position'],
            'dept_id'   => $validated['dept_id'],
            'state'     => $validated['state'] ? 1 : 0,
        ]);

        return redirect()->back()->with('success', 'Employee updated successfully.');
    }

    public function destroy($id)
    {
        $employee = CityEmployee::findOrFail($id);
        $employee->delete();

        return redirect()->back()->with('success', 'Employee removed successfully.');
    }
}