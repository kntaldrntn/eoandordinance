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
            // Make these nullable, but require at least one using `required_without`
            'executive_order_id' => 'nullable|required_without:ordinance_id|exists:executive_orders,id',
            'ordinance_id'       => 'nullable|required_without:executive_order_id|exists:ordinances,id',
            
            'lead_office_id' => 'required|exists:departments,id',
            'status' => 'required|string',
            'file' => 'required|file|mimes:pdf|max:10240',
        ]);

        $path = $request->file('file')->store('irrs', 'public');

        ImplementingRuleandRegulation::create([
            'executive_order_id' => $validated['executive_order_id'] ?? null,
            'ordinance_id'       => $validated['ordinance_id'] ?? null, 
            'lead_office_id'     => $validated['lead_office_id'],
            'status'             => $validated['status'],
            'file_path'          => $path,
        ]);

        return redirect()->back()->with('success', 'IRR uploaded successfully.');
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