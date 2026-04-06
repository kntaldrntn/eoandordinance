<?php

namespace App\Http\Controllers;

use App\Models\ImplementingRuleandRegulation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage; // <--- Import this!

class IRRController extends Controller
{
    public function store(Request $request)
     {
        $validated = $request->validate([
            'ordinance_id' => 'nullable|exists:ordinances,id',
            'executive_order_id' => 'nullable|exists:executive_orders,id',
            'lead_office_id' => 'required|exists:departments,id',
            'support_office_ids' => 'nullable|array', 
            'status' => 'required|string|in:Active,On-hold,Dropped',
            'file' => 'required|file|mimes:pdf|max:20480',
        ]);

        $path = $request->file('file')->store('irrs', 'public');

        ImplementingRuleandRegulation::create([
            'ordinance_id' => $validated['ordinance_id'] ?? null,
            'executive_order_id' => $validated['executive_order_id'] ?? null,
            'lead_office_id' => $validated['lead_office_id'],
            'support_office_ids' => $validated['support_office_ids'] ?? [], 
            'status' => $validated['status'],
            'file_path' => $path,
        ]);

        return redirect()->back()->with('success', 'IRR added successfully.');
    }

    // --- NEW DELETE FUNCTION ---
    public function destroy($id)
    {
        // 1. Find the Record
        $irr = ImplementingRuleandRegulation::findOrFail($id);

        // 2. Delete the physical PDF file from storage
        if ($irr->file_path && Storage::disk('public')->exists($irr->file_path)) {
            Storage::disk('public')->delete($irr->file_path);
        }

        // 3. Delete the Database Record
        $irr->delete();

        return redirect()->back()->with('success', 'IRR deleted successfully.');
    }
}