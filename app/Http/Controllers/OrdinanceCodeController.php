<?php

namespace App\Http\Controllers;

use App\Models\OrdinanceCode;
use Illuminate\Http\Request;
use Inertia\Inertia;

class OrdinanceCodeController extends Controller
{
    public function index()
    {
        return Inertia::render('ordinance_codes/Index', [
            'ordinance_codes' => OrdinanceCode::withCount('ordinances')->orderBy('name')->get(),
            'flash' => [
                'success' => session('success'),
                'error' => session('error'),
                'delete' => session('delete'),
            ],
        ]);
    }
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|unique:ordinance_codes,name',
            'description' => 'nullable|string'
        ]);

        OrdinanceCode::create($validated);
        return redirect()->back()->with('success', 'Code created.');
    }

    public function update(Request $request, OrdinanceCode $ordinanceCode)
    {
        $validated = $request->validate([
            'name' => 'required|string|unique:ordinance_codes,name,' . $ordinanceCode->id,
            'description' => 'nullable|string'
        ]);

        $ordinanceCode->update($validated);
        return redirect()->back()->with('success', 'Code updated.');
    }

    public function destroy(OrdinanceCode $ordinanceCode)
    {
        $ordinanceCode->delete();
        return redirect()->back()->with('delete', 'Code deleted.');
    }
}