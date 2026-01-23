<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB; // Using DB facade for simple tables is often faster
use Inertia\Inertia;

class StatusController extends Controller
{
    public function index(Request $request)
    {
        $query = DB::table('statuses');

        if ($request->filled('search')) {
            $query->where('name', 'LIKE', "%{$request->search}%");
        }

        $statuses = $query->orderBy('id', 'asc')->paginate(5)->withQueryString();

        return Inertia::render('statuses/Index', [
            'statuses' => $statuses,
            'filters' => $request->only(['search']),
            'flash' => [
                'success' => session('success'),
                'error' => session('error'),
            ],
        ]);
    }

    public function store(Request $request)
    {
        $request->validate(['name' => 'required|string|max:50|unique:statuses,name']);

        DB::table('statuses')->insert([
            'name' => $request->name,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return redirect()->back()->with('success', 'Status created.');
    }

    public function update(Request $request, $id)
    {
        $request->validate(['name' => 'required|string|max:50']);

        DB::table('statuses')->where('id', $id)->update([
            'name' => $request->name,
            'updated_at' => now(),
        ]);

        return redirect()->back()->with('success', 'Status updated.');
    }

    public function destroy($id)
    {
        DB::table('statuses')->where('id', $id)->delete();
        return redirect()->back()->with('success', 'Status deleted.');
    }
}