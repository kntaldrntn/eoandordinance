<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB; 
use Illuminate\Http\Request;
use Inertia\Inertia;

class ClassificationController extends Controller
{
    public function index(Request $request)
    {
        $query = DB::table('classifications');

        if ($request->filled('search')) {
            $query->where('name', 'LIKE', "%{$request->search}%");
        }

        $classifications = $query->orderBy('id', 'asc')->paginate(5)->withQueryString();

        return Inertia::render('classifications/Index', [
            'classifications' => $classifications,
            'filters' => $request->only(['search']),
            'flash' => [
                'success' => session('success'),
                'error' => session('error'),
            ],
        ]);
    }
    public function store(Request $request)
    {
        $request->validate(['name' => 'required|string|max:50|unique:classifications,name']);

        DB::table('classifications')->insert([
            'name' => $request->name,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return redirect()->back()->with('success', 'Classification created.');
    }
    public function update(Request $request, $id)
    {
        $request->validate(['name' => 'required|string|max:50']);

        DB::table('classifications')->where('id', $id)->update([
            'name' => $request->name,
            'updated_at' => now(),
        ]);

        return redirect()->back()->with('success', 'Classification updated.');
    }
    public function destroy($id)
    {
        DB::table('classifications')->where('id', $id)->delete();
        return redirect()->back()->with('success', 'Classification deleted.');
    }
}
